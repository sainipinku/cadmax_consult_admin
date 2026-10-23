<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLocation;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminEmployeeLocationController extends Controller
{
    public function renderView(Request $request): InertiaResponse
    {
        $currentUser = Auth::guard('superadmin')->user()
            ?? Auth::guard('admin')->user()
            ?? Auth::guard('member')->user()
            ?? Auth::user();

        $isProjectOwner = false;
        if ($currentUser instanceof SuperAdmin) {
            $isProjectOwner = ((int) $currentUser->id === 1 || (string) $currentUser->id === '1' || (isset($currentUser->is_project_owner) && (int) $currentUser->is_project_owner === 1));
        }

        $superAdmins = [];
        if ($isProjectOwner) {
            $superAdmins = SuperAdmin::with('company')
                ->orderBy('name')
                ->get()
                ->map(fn (SuperAdmin $sa) => [
                    'id' => $sa->id,
                    'name' => $sa->name,
                    'email' => $sa->email,
                    'company_name' => $sa->company?->name,
                    'label' => $sa->name . ($sa->company?->name ? ' (' . $sa->company->name . ')' : '') . (((int) $sa->id === 1 || (string) $sa->id === '1') ? ' [Project Owner]' : ''),
                ])
                ->values()
                ->all();
        }

        return Inertia::render('SuperAdmin/LocationTracking', [
            'isProjectOwner' => (bool) $isProjectOwner,
            'superAdmins' => $superAdmins,
            'currentSuperAdmin' => $currentUser ? [
                'id' => $currentUser->id,
                'name' => $currentUser->name,
                'email' => $currentUser->email,
                'company_id' => $currentUser->company_id ?? null,
                'company_name' => $currentUser->company?->name ?? null,
            ] : null,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $currentUser = Auth::guard('superadmin')->user()
            ?? Auth::guard('admin')->user()
            ?? Auth::guard('member')->user()
            ?? Auth::user();

        $isProjectOwner = false;
        if ($currentUser instanceof SuperAdmin) {
            $isProjectOwner = ((int) $currentUser->id === 1 || (string) $currentUser->id === '1' || (isset($currentUser->is_project_owner) && (int) $currentUser->is_project_owner === 1));
        }

        // Determine which SuperAdmin to scope by
        $targetSuperAdminId = null;
        if (!$isProjectOwner) {
            if ($currentUser instanceof SuperAdmin) {
                $targetSuperAdminId = (int) $currentUser->id;
            } elseif ($currentUser instanceof Member) {
                $targetSuperAdminId = $this->resolveOwningSuperAdminIdForMember($currentUser);
            }
        } else {
            if ($request->filled('super_admin_id')) {
                $targetSuperAdminId = (int) $request->input('super_admin_id');
            }
        }

        $hasLocationTable = Schema::hasTable('employee_locations');
        $hasMemberCoordinates = Schema::hasColumn('members', 'latitude')
            && Schema::hasColumn('members', 'longitude');
        $hasMemberLocationTimestamp = Schema::hasColumn('members', 'location_captured_at');
        $relations = [
            'member.latestAttendanceLocation',
        ];
        if ($hasLocationTable) {
            $relations[] = 'member.latestLocation';
        }

        $query = Employee::query()
            ->with($relations)
            ->whereHas('member', fn ($query) => $query->where('status', 1));

        if ($targetSuperAdminId) {
            $this->applySuperAdminScope($query, $targetSuperAdminId);
        }

        $allSuperAdmins = SuperAdmin::with('company')->get();
        $superAdminsMap = $allSuperAdmins->keyBy('id');

        $employees = $query->get()
            ->map(function (Employee $employee) use ($hasLocationTable, $hasMemberCoordinates, $hasMemberLocationTimestamp, $superAdminsMap): ?array {
                $member = $employee->member;
                if (!$member) {
                    return null;
                }

                $location = $hasLocationTable ? $member->latestLocation : null;
                $locationIsValid = $location && $this->hasValidCoordinates($location);
                $attendance = $member?->latestAttendanceLocation;
                $attendanceLocation = $attendance ? $this->attendanceLocation($attendance) : null;
                $memberLocation = null;
                if ($hasMemberCoordinates && $this->validCoordinates($member->latitude, $member->longitude)) {
                    $memberLocation = [
                        'latitude' => $member->latitude,
                        'longitude' => $member->longitude,
                        'accuracy' => null,
                        'capturedAt' => $hasMemberLocationTimestamp ? $member->location_captured_at : null,
                        'source' => 'member',
                    ];
                }

                $candidates = array_filter([
                    $locationIsValid ? [
                        'latitude' => $location->latitude,
                        'longitude' => $location->longitude,
                        'accuracy' => $location->accuracy,
                        'capturedAt' => $location->captured_at,
                        'source' => 'reported',
                    ] : null,
                    $attendanceLocation ? [...$attendanceLocation, 'source' => 'attendance'] : null,
                    $memberLocation,
                ]);
                usort($candidates, function (array $left, array $right): int {
                    if (!$left['capturedAt']) return 1;
                    if (!$right['capturedAt']) return -1;

                    return $right['capturedAt']->getTimestamp() <=> $left['capturedAt']->getTimestamp();
                });
                $position = $candidates[0] ?? null;
                if (!$position) {
                    return null;
                }
                $capturedAt = $position['capturedAt'];

                $assignedSa = $this->resolveMemberSuperAdmin($member, $superAdminsMap);

                return [
                    'employeeId' => $employee->employee_id,
                    'name' => $member->name,
                    'profileImage' => $member->profile_photo_url,
                    'designation' => $member->designation_names ?: 'Not specified',
                    'department' => $member->department_names ?: 'Not specified',
                    'latitude' => (float) $position['latitude'],
                    'longitude' => (float) $position['longitude'],
                    'accuracy' => $position['accuracy'] === null ? null : (float) $position['accuracy'],
                    'capturedAt' => $capturedAt?->toIso8601String(),
                    'status' => $this->reportingStatus($capturedAt),
                    'locationSource' => $position['source'],
                    'phone' => $member->phone ?: $member->mobile,
                    'email' => $member->email,
                    'superAdminId' => $assignedSa?->id,
                    'superAdminName' => $assignedSa?->name,
                    'companyName' => $assignedSa?->company?->name ?? $member->company_name,
                    'role' => $member->role_names ?: ($member->is_admin ? 'Admin' : 'Member'),
                ];
            })
            ->filter()
            ->values();

        $superAdminsList = [];
        if ($isProjectOwner) {
            $superAdminsList = $allSuperAdmins->map(fn (SuperAdmin $sa) => [
                'id' => $sa->id,
                'name' => $sa->name,
                'email' => $sa->email,
                'company_name' => $sa->company?->name,
                'label' => $sa->name . ($sa->company?->name ? ' (' . $sa->company->name . ')' : '') . (((int) $sa->id === 1 || (string) $sa->id === '1') ? ' [Project Owner]' : ''),
            ])->values()->all();
        }

        return response()->json([
            'success' => true,
            'data' => $employees,
            'reportingIntervalSeconds' => $this->reportingInterval(),
            'isProjectOwner' => (bool) $isProjectOwner,
            'selectedSuperAdminId' => $targetSuperAdminId,
            'superAdmins' => $superAdminsList,
        ]);
    }

    public function history(Request $request, string $employeeId): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : now()->startOfDay();
        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : now()->endOfDay();
        if (isset($validated['from']) && strlen($validated['from']) === 10) {
            $from->startOfDay();
        }
        if (isset($validated['to']) && strlen($validated['to']) === 10) {
            $to->endOfDay();
        }

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages([
                'from' => 'The start date must be before or equal to the end date.',
            ]);
        }

        $employee = Employee::query()
            ->where('employee_id', $employeeId)
            ->whereHas('member', fn ($query) => $query->where('status', 1))
            ->firstOrFail();

        $currentUser = Auth::guard('superadmin')->user()
            ?? Auth::guard('admin')->user()
            ?? Auth::guard('member')->user()
            ?? Auth::user();

        $isProjectOwner = false;
        if ($currentUser instanceof SuperAdmin) {
            $isProjectOwner = ((int) $currentUser->id === 1 || (string) $currentUser->id === '1' || (isset($currentUser->is_project_owner) && (int) $currentUser->is_project_owner === 1));
        }

        if (!$isProjectOwner && $currentUser instanceof SuperAdmin) {
            $targetSuperAdminId = (int) $currentUser->id;
            $hasAccess = Employee::query()
                ->where('id', $employee->id)
                ->where(function ($q) use ($targetSuperAdminId) {
                    $this->applySuperAdminScope($q, $targetSuperAdminId);
                })
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this employee\'s location history.',
                ], 403);
            }
        }

        $locations = collect();
        if (Schema::hasTable('employee_locations')) {
            $locations = EmployeeLocation::query()
                ->where('member_id', $employee->member_id)
                ->whereBetween('captured_at', [$from, $to])
                ->orderBy('captured_at')
                ->orderBy('id')
                ->limit(5000)
                ->get()
                ->filter(fn (EmployeeLocation $location) => $this->hasValidCoordinates($location))
                ->map(fn (EmployeeLocation $location) => [
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'accuracy' => $location->accuracy === null ? null : (float) $location->accuracy,
                    'capturedAt' => $location->captured_at,
                ]);
        }

        $attendanceRecords = \App\Models\AttendanceRecord::query()
            ->where('member_id', $employee->member_id)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('attendance_date')
            ->get();
        foreach ($attendanceRecords as $record) {
            foreach ([
                [$record->check_in_latitude, $record->check_in_longitude, $record->check_in_at],
                [$record->check_out_latitude, $record->check_out_longitude, $record->check_out_at],
            ] as [$latitude, $longitude, $capturedAt]) {
                if (
                    !$capturedAt
                    || !$capturedAt->between($from, $to)
                    || !$this->validCoordinates($latitude, $longitude)
                ) {
                    continue;
                }

                $locations->push([
                    'latitude' => (float) $latitude,
                    'longitude' => (float) $longitude,
                    'accuracy' => $record->gps_accuracy_meters === null
                        ? null
                        : (float) $record->gps_accuracy_meters,
                    'capturedAt' => $capturedAt,
                ]);
            }
        }

        if (
            Schema::hasColumn('members', 'latitude')
            && Schema::hasColumn('members', 'longitude')
            && Schema::hasColumn('members', 'location_captured_at')
        ) {
            $member = $employee->member;
            $capturedAt = $member->location_captured_at;
            if (
                $capturedAt
                && $capturedAt->between($from, $to)
                && $this->validCoordinates($member->latitude, $member->longitude)
            ) {
                $locations->push([
                    'latitude' => (float) $member->latitude,
                    'longitude' => (float) $member->longitude,
                    'accuracy' => null,
                    'capturedAt' => $capturedAt,
                ]);
            }
        }

        $locations = $locations
            ->sortBy(fn (array $location) => $location['capturedAt']->getTimestamp())
            ->take(5000)
            ->values()
            ->map(fn (array $location) => [
                ...$location,
                'capturedAt' => $location['capturedAt']->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'employee' => [
                'employeeId' => $employee->employee_id,
                'name' => $employee->member->name,
                'profileImage' => $employee->member->profile_photo_url,
            ],
            'data' => $locations,
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $member = $request->user();
        if (!$member instanceof Member || (int) $member->status !== 1) {
            return response()->json(['success' => false, 'message' => 'An active employee account is required.'], 403);
        }

        $employee = $member->employee;
        if (!$employee && class_exists(Employee::class)) {
            $employee = Employee::firstOrCreate(
                ['member_id' => $member->getKey()],
                ['uuid' => (string) \Illuminate\Support\Str::uuid()]
            );
            $member->setRelation('employee', $employee);
        }

        // Handle batch reporting
        if ($request->has('locations') && is_array($request->input('locations'))) {
            $locations = $request->input('locations');
            $recordedCount = 0;
            $lastRecord = null;

            foreach ($locations as $loc) {
                if (!is_array($loc)) continue;
                $lat = $loc['latitude'] ?? $loc['lat'] ?? null;
                $long = $loc['longitude'] ?? $loc['lng'] ?? $loc['long'] ?? $loc['lon'] ?? null;
                $accuracy = $loc['accuracy'] ?? $loc['gps_accuracy_meters'] ?? $loc['gps_accuracy'] ?? null;
                $time = $loc['captured_at'] ?? $loc['capturedAt'] ?? $loc['timestamp'] ?? $loc['time'] ?? now();

                if (is_numeric($lat) && is_numeric($long) && $lat >= -90 && $lat <= 90 && $long >= -180 && $long <= 180) {
                    $saved = EmployeeLocation::recordIfValid($member, $lat, $long, $accuracy, $time);
                    if ($saved) {
                        $recordedCount++;
                        $lastRecord = $saved;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully recorded {$recordedCount} location points.",
                'data' => [
                    'member_id' => $member->id,
                    'employee_id' => $employee?->employee_id,
                    'count' => $recordedCount,
                    'latest_latitude' => $lastRecord ? (float) $lastRecord->latitude : null,
                    'latest_longitude' => $lastRecord ? (float) $lastRecord->longitude : null,
                    'latest_accuracy' => $lastRecord && $lastRecord->accuracy !== null ? (float) $lastRecord->accuracy : null,
                    'latest_captured_at' => $lastRecord ? $lastRecord->captured_at?->toIso8601String() : null,
                    'sync_interval_minutes' => 20,
                ],
            ]);
        }

        $lat = $request->input('latitude', $request->input('lat'));
        $long = $request->input('longitude', $request->input('lng', $request->input('long', $request->input('lon'))));
        $accuracy = $request->input('accuracy', $request->input('gps_accuracy_meters', $request->input('gps_accuracy')));
        $rawCapturedAt = $request->input('captured_at', $request->input('capturedAt', $request->input('timestamp', $request->input('time'))));

        if (!is_numeric($lat) || !is_numeric($long) || $lat < -90 || $lat > 90 || $long < -180 || $long > 180) {
            throw ValidationException::withMessages([
                'latitude' => ['The latitude field is required and must be between -90 and 90.'],
                'longitude' => ['The longitude field is required and must be between -180 and 180.'],
            ]);
        }

        try {
            $capturedAt = $rawCapturedAt ? Carbon::parse($rawCapturedAt) : now();
        } catch (\Throwable $e) {
            $capturedAt = now();
        }

        if ($capturedAt->greaterThan(now()->addMinutes(10))) {
            throw ValidationException::withMessages([
                'capturedAt' => ['The location capture time cannot be more than ten minutes in the future.'],
            ]);
        }

        if (
            !Schema::hasTable('employee_locations')
            && !(Schema::hasColumn('members', 'latitude')
                && Schema::hasColumn('members', 'longitude')
                && Schema::hasColumn('members', 'location_captured_at'))
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Run the employee location database migrations before reporting GPS locations.',
            ], 503);
        }

        $record = EmployeeLocation::recordIfValid(
            $member,
            $lat,
            $long,
            $accuracy,
            $capturedAt
        );

        return response()->json([
            'success' => true,
            'message' => 'Location recorded successfully.',
            'data' => [
                'id' => $record?->id,
                'member_id' => $member->id,
                'employee_id' => $employee?->employee_id,
                'latitude' => (float) $lat,
                'longitude' => (float) $long,
                'accuracy' => is_numeric($accuracy) ? (float) $accuracy : null,
                'capturedAt' => $capturedAt->toIso8601String(),
                'captured_at' => $capturedAt->toIso8601String(),
                'sync_interval_minutes' => 20,
            ],
        ], 201);
    }

    private function hasValidCoordinates(EmployeeLocation $location): bool
    {
        return $this->validCoordinates($location->latitude, $location->longitude);
    }

    private function validCoordinates(mixed $latitude, mixed $longitude): bool
    {
        return is_numeric($latitude)
            && is_numeric($longitude)
            && $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180;
    }

    private function attendanceLocation(\App\Models\AttendanceRecord $record): ?array
    {
        $checkInValid = is_numeric($record->check_in_latitude)
            && is_numeric($record->check_in_longitude)
            && $record->check_in_latitude >= -90
            && $record->check_in_latitude <= 90
            && $record->check_in_longitude >= -180
            && $record->check_in_longitude <= 180;
        $checkOutValid = is_numeric($record->check_out_latitude)
            && is_numeric($record->check_out_longitude)
            && $record->check_out_latitude >= -90
            && $record->check_out_latitude <= 90
            && $record->check_out_longitude >= -180
            && $record->check_out_longitude <= 180;

        if (!$checkInValid && !$checkOutValid) {
            return null;
        }

        $useCheckOut = $checkOutValid
            && (!$checkInValid || !$record->check_in_at || $record->check_out_at?->greaterThanOrEqualTo($record->check_in_at));
        $capturedAt = $useCheckOut
            ? ($record->check_out_at ?? $record->check_in_at)
            : $record->check_in_at;
        $capturedAt ??= Carbon::parse($record->attendance_date)->startOfDay();

        return [
            'latitude' => $useCheckOut ? $record->check_out_latitude : $record->check_in_latitude,
            'longitude' => $useCheckOut ? $record->check_out_longitude : $record->check_in_longitude,
            'accuracy' => $record->gps_accuracy_meters,
            'capturedAt' => $capturedAt,
        ];
    }

    private function reportingStatus(?Carbon $capturedAt): string
    {
        if (!$capturedAt) {
            return 'Offline / not reporting';
        }

        $ageSeconds = max(0, $capturedAt->diffInSeconds(now()));
        $interval = $this->reportingInterval();

        if ($ageSeconds <= $interval * 2) {
            return 'Recently updated';
        }

        return $ageSeconds <= $interval * 10 ? 'Stale' : 'Offline / not reporting';
    }

    public function directions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coordinates' => ['required', 'array', 'min:2'],
            'coordinates.*' => ['required'],
        ]);

        $apiKey = config('services.openrouteservice.api_key') ?: env('OPENROUTESERVICE_API_KEY');
        $rawCoordinates = $validated['coordinates'];

        $validPairs = [];
        foreach ($rawCoordinates as $coord) {
            $lat = null;
            $lng = null;
            if (is_array($coord)) {
                if (isset($coord['lat']) && isset($coord['lng'])) {
                    $lat = (float) $coord['lat'];
                    $lng = (float) $coord['lng'];
                } elseif (isset($coord[0]) && isset($coord[1])) {
                    $lat = (float) $coord[0];
                    $lng = (float) $coord[1];
                }
            }
            if ($lat !== null && $lng !== null && $this->validCoordinates($lat, $lng)) {
                // OpenRouteService expects [longitude, latitude]
                $validPairs[] = [$lng, $lat];
            }
        }

        if (count($validPairs) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'At least two valid coordinate pairs are required.',
            ], 422);
        }

        $directCoordinates = array_map(fn ($p) => ['lat' => $p[1], 'lng' => $p[0]], $validPairs);

        if (empty($apiKey)) {
            return response()->json([
                'success' => true,
                'source' => 'direct',
                'coordinates' => $directCoordinates,
            ]);
        }

        // Downsample if more than 50 points to respect ORS limit
        $orsCoordinates = $this->downsampleCoordinates($validPairs, 50);

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(6)
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, application/geo+json',
                ])
                ->post('https://api.openrouteservice.org/v2/directions/driving-car/geojson', [
                    'coordinates' => $orsCoordinates,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $feature = $data['features'][0] ?? null;
                $geometry = $feature['geometry'] ?? null;
                $summary = $feature['properties']['summary'] ?? null;

                $routePath = [];
                if (isset($geometry['coordinates']) && is_array($geometry['coordinates'])) {
                    foreach ($geometry['coordinates'] as $point) {
                        $routePath[] = [
                            'lat' => (float) $point[1],
                            'lng' => (float) $point[0],
                        ];
                    }
                }

                if (!empty($routePath)) {
                    return response()->json([
                        'success' => true,
                        'source' => 'openrouteservice',
                        'distance_meters' => $summary['distance'] ?? null,
                        'distance_km' => isset($summary['distance']) ? round($summary['distance'] / 1000, 2) : null,
                        'duration_seconds' => $summary['duration'] ?? null,
                        'duration_minutes' => isset($summary['duration']) ? round($summary['duration'] / 60, 1) : null,
                        'coordinates' => $routePath,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('OpenRouteService directions error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'source' => 'direct',
            'coordinates' => $directCoordinates,
        ]);
    }

    private function downsampleCoordinates(array $coordinates, int $maxPoints = 50): array
    {
        $count = count($coordinates);
        if ($count <= $maxPoints) {
            return $coordinates;
        }

        $sampled = [$coordinates[0]];
        $step = ($count - 1) / ($maxPoints - 1);
        for ($i = 1; $i < $maxPoints - 1; $i++) {
            $index = (int) round($i * $step);
            $sampled[] = $coordinates[$index];
        }
        $sampled[] = $coordinates[$count - 1];

        return $sampled;
    }

    private function reportingInterval(): int
    {
        return max(30, (int) config('location_tracking.reporting_interval_seconds', 60));
    }

    public function applySuperAdminScope($query, int $targetSuperAdminId): void
    {
        $targetSa = SuperAdmin::with('company')->find($targetSuperAdminId);
        $companyId = $targetSa?->company_id;
        $companyName = $targetSa?->company?->name;

        $companySuperAdminIds = $companyId
            ? SuperAdmin::where('company_id', $companyId)->pluck('id')->all()
            : [$targetSuperAdminId];

        if (empty($companySuperAdminIds)) {
            $companySuperAdminIds = [$targetSuperAdminId];
        }

        $directMemberIds = Member::whereIn('created_by', $companySuperAdminIds)->pluck('id')->all();
        $subMemberIds = !empty($directMemberIds)
            ? Member::whereIn('created_by', $directMemberIds)->pluck('id')->all()
            : [];
        $allowedCreatorIds = array_values(array_unique(array_filter(array_merge($companySuperAdminIds, $directMemberIds, $subMemberIds))));

        $query->whereHas('member', function ($mq) use ($allowedCreatorIds, $companyName, $targetSuperAdminId) {
            $mq->where(function ($sq) use ($allowedCreatorIds, $companyName, $targetSuperAdminId) {
                if (!empty($allowedCreatorIds)) {
                    $sq->whereIn('created_by', $allowedCreatorIds);
                }
                $sq->orWhere('created_by', $targetSuperAdminId);

                if (!empty($companyName)) {
                    $sq->orWhere('company_name', $companyName);
                }
            });
        });
    }

    protected function resolveMemberSuperAdmin(Member $member, $superAdminsMap): ?SuperAdmin
    {
        if ($member->created_by && $superAdminsMap->has($member->created_by)) {
            return $superAdminsMap->get($member->created_by);
        }

        if (!empty($member->company_name)) {
            $matchedByCompany = $superAdminsMap->first(function ($sa) use ($member) {
                return $sa->company && strcasecmp(trim($sa->company->name), trim($member->company_name)) === 0;
            });
            if ($matchedByCompany) {
                return $matchedByCompany;
            }
        }

        if ($member->created_by) {
            $parent = Member::find($member->created_by);
            if ($parent && $parent->created_by && $superAdminsMap->has($parent->created_by)) {
                return $superAdminsMap->get($parent->created_by);
            }
            if ($parent && !empty($parent->company_name)) {
                $matchedParent = $superAdminsMap->first(function ($sa) use ($parent) {
                    return $sa->company && strcasecmp(trim($sa->company->name), trim($parent->company_name)) === 0;
                });
                if ($matchedParent) {
                    return $matchedParent;
                }
            }
        }

        return $superAdminsMap->get(1) ?? $superAdminsMap->first();
    }

    protected function resolveOwningSuperAdminIdForMember(Member $member): ?int
    {
        $allSuperAdmins = SuperAdmin::with('company')->get();
        $superAdminsMap = $allSuperAdmins->keyBy('id');
        $sa = $this->resolveMemberSuperAdmin($member, $superAdminsMap);
        return $sa?->id;
    }
}
