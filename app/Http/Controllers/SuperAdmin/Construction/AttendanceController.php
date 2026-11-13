<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Project;
use App\Models\SuperAdmin;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    use ResolvesConstructionActor;

    /**
     * Display the Project Attendance Management panel with dynamic project selector.
     */
    public function index(Request $request): Response
    {
        $actor = $this->constructionActor();
        $isProjectOwner = $this->isProjectOwner($actor);
        $accessibleProjectIds = $this->getAccessibleProjectIds($actor);

        $companies = collect();
        if ($isProjectOwner) {
            $companies = Company::orderBy('name')->get(['id', 'name']);
        }

        $projectsQuery = Project::with('company')
            ->whereIn('id', $accessibleProjectIds);

        if ($isProjectOwner && $request->filled('company_id')) {
            $projectsQuery->where('company_id', (int) $request->input('company_id'));
        }

        $projects = $projectsQuery->orderBy('name')
            ->get(['id', 'company_id', 'client_id', 'name', 'project_code', 'status']);

        $selectedProjectId = $request->filled('project_id')
            ? (int) $request->input('project_id')
            : null;

        // Base query for attendance records
        $recordsQuery = AttendanceRecord::with([
            'project.company',
            'member.employee',
            'reviewedBy',
        ])->whereIn('project_id', $accessibleProjectIds);

        if ($selectedProjectId) {
            $recordsQuery->where('project_id', $selectedProjectId);
        }

        // Company SuperAdmin scope: ensure member belongs to company
        if (!$isProjectOwner && $actor instanceof SuperAdmin && $actor->company_id) {
            $recordsQuery->whereHas('project', function ($q) use ($actor) {
                $q->where('company_id', $actor->company_id);
            });
        }

        // Date filtering
        $dateFilter = $request->input('date', 'all');
        if ($dateFilter !== 'all' && !empty($dateFilter)) {
            if ($dateFilter === 'today') {
                $recordsQuery->whereDate('attendance_date', Carbon::today());
            } elseif ($dateFilter === 'yesterday') {
                $recordsQuery->whereDate('attendance_date', Carbon::yesterday());
            } else {
                $recordsQuery->whereDate('attendance_date', $dateFilter);
            }
        }

        if ($request->filled('from_date')) {
            $recordsQuery->whereDate('attendance_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $recordsQuery->whereDate('attendance_date', '<=', $request->input('to_date'));
        }

        // Status filter
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $status = strtolower((string) $request->input('status'));
            if ($status === 'in_progress') {
                $recordsQuery->whereNotNull('check_in_at')->whereNull('check_out_at');
            } elseif ($status === 'completed') {
                $recordsQuery->whereNotNull('check_in_at')->whereNotNull('check_out_at');
            } else {
                $recordsQuery->where('status', $status);
            }
        }

        // Search term
        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $recordsQuery->where(function ($q) use ($term) {
                $q->whereHas('member', function ($mq) use ($term) {
                    $mq->where('name', 'like', "%{$term}%")
                       ->orWhere('email', 'like', "%{$term}%")
                       ->orWhere('mobile', 'like', "%{$term}%");
                })->orWhereHas('project', function ($pq) use ($term) {
                    $pq->where('name', 'like', "%{$term}%")
                       ->orWhere('project_code', 'like', "%{$term}%");
                });
            });
        }

        $allRecords = $recordsQuery->latest('attendance_date')->latest('check_in_at')->get();

        // Calculate KPIs
        $presentToday = AttendanceRecord::whereIn('project_id', $accessibleProjectIds)
            ->whereDate('attendance_date', Carbon::today())
            ->count();

        $checkedOutToday = AttendanceRecord::whereIn('project_id', $accessibleProjectIds)
            ->whereDate('attendance_date', Carbon::today())
            ->whereNotNull('check_out_at')
            ->count();

        $pendingApproval = AttendanceRecord::whereIn('project_id', $accessibleProjectIds)
            ->where('status', 'pending')
            ->count();

        // Transform records
        $records = $allRecords->map(function (AttendanceRecord $record) {
            $durationData = $this->calculateDuration($record->check_in_at, $record->check_out_at);
            $distance = $this->haversineDistance(
                $record->check_in_latitude,
                $record->check_in_longitude,
                $record->check_out_latitude,
                $record->check_out_longitude
            );

            return [
                'id' => $record->id,
                'project_id' => $record->project_id,
                'project_name' => $record->project?->name,
                'project_code' => $record->project?->project_code,
                'company_name' => $record->project?->company?->name,
                'member_id' => $record->member_id,
                'employee' => [
                    'id' => $record->member?->id,
                    'name' => $record->member?->name ?? 'Unknown',
                    'email' => $record->member?->email,
                    'phone' => $record->member?->phone ?? $record->member?->mobile,
                    'avatar' => $record->member?->profile_photo_url,
                    'employee_code' => $record->member?->employee?->employee_id ?? ('EMP-' . $record->member_id),
                    'designation' => $record->member?->designation_names ?: 'Site Employee',
                ],
                'attendance_date' => $record->attendance_date?->format('Y-m-d'),
                'attendance_date_formatted' => $record->attendance_date?->format('d M Y'),
                'check_in_at' => $record->check_in_at?->toIso8601String(),
                'check_in_formatted' => $record->check_in_at?->format('h:i A'),
                'check_out_at' => $record->check_out_at?->toIso8601String(),
                'check_out_formatted' => $record->check_out_at ? $record->check_out_at->format('h:i A') : null,
                'check_in_latitude' => $record->check_in_latitude ? (float) $record->check_in_latitude : null,
                'check_in_longitude' => $record->check_in_longitude ? (float) $record->check_in_longitude : null,
                'check_out_latitude' => $record->check_out_latitude ? (float) $record->check_out_latitude : null,
                'check_out_longitude' => $record->check_out_longitude ? (float) $record->check_out_longitude : null,
                'gps_accuracy_meters' => $record->gps_accuracy_meters ? (float) $record->gps_accuracy_meters : null,
                'duration_minutes' => $durationData['minutes'],
                'duration_hours' => $durationData['hours'],
                'duration_formatted' => $durationData['formatted'],
                'is_in_progress' => $durationData['is_in_progress'],
                'distance_meters' => $distance,
                'status' => $record->status ?? 'pending',
                'attendance_type' => $record->attendance_type ?? 'present',
                'notes' => $record->notes,
                'reviewed_by' => $record->reviewedBy?->name,
                'reviewed_at' => $record->reviewed_at?->format('d M Y h:i A'),
                'review_notes' => $record->review_notes,
            ];
        });

        return Inertia::render('SuperAdmin/Construction/Attendance/Index', [
            'isProjectOwner' => $isProjectOwner,
            'companies' => $companies,
            'projects' => $projects,
            'selectedProjectId' => $selectedProjectId,
            'filters' => [
                'company_id' => $request->input('company_id'),
                'project_id' => $selectedProjectId,
                'date' => $dateFilter,
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'status' => $request->input('status', 'all'),
                'search' => $request->input('search'),
            ],
            'kpis' => [
                'totalRecords' => $allRecords->count(),
                'presentToday' => $presentToday,
                'checkedOutToday' => $checkedOutToday,
                'pendingApproval' => $pendingApproval,
            ],
            'attendanceRecords' => $records,
        ]);
    }

    /**
     * Return detailed modal data including GPS coordinates, duration, and map data.
     */
    public function show(AttendanceRecord $attendance, Request $request): JsonResponse
    {
        $actor = $this->constructionActor();
        $accessibleProjectIds = $this->getAccessibleProjectIds($actor);

        if (!$accessibleProjectIds->contains($attendance->project_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: This attendance record belongs to a project outside your scope.',
            ], 403);
        }

        $attendance->load(['project.company', 'member.employee', 'reviewedBy']);

        $durationData = $this->calculateDuration($attendance->check_in_at, $attendance->check_out_at);
        $distance = $this->haversineDistance(
            $attendance->check_in_latitude,
            $attendance->check_in_longitude,
            $attendance->check_out_latitude,
            $attendance->check_out_longitude
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $attendance->id,
                'project' => [
                    'id' => $attendance->project?->id,
                    'name' => $attendance->project?->name,
                    'project_code' => $attendance->project?->project_code,
                    'company_name' => $attendance->project?->company?->name,
                ],
                'employee' => [
                    'id' => $attendance->member?->id,
                    'name' => $attendance->member?->name ?? 'Employee',
                    'email' => $attendance->member?->email,
                    'phone' => $attendance->member?->phone ?? $attendance->member?->mobile,
                    'avatar' => $attendance->member?->profile_photo_url,
                    'employee_code' => $attendance->member?->employee?->employee_id ?? ('EMP-' . $attendance->member_id),
                    'designation' => $attendance->member?->designation_names ?: 'Site Employee',
                ],
                'attendance_date' => $attendance->attendance_date?->format('Y-m-d'),
                'attendance_date_formatted' => $attendance->attendance_date?->format('d M Y'),
                'check_in' => [
                    'timestamp' => $attendance->check_in_at?->toIso8601String(),
                    'formatted' => $attendance->check_in_at?->format('d M Y, h:i A'),
                    'latitude' => $attendance->check_in_latitude ? (float) $attendance->check_in_latitude : null,
                    'longitude' => $attendance->check_in_longitude ? (float) $attendance->check_in_longitude : null,
                ],
                'check_out' => [
                    'timestamp' => $attendance->check_out_at?->toIso8601String(),
                    'formatted' => $attendance->check_out_at ? $attendance->check_out_at->format('d M Y, h:i A') : 'Active / In Progress',
                    'latitude' => $attendance->check_out_latitude ? (float) $attendance->check_out_latitude : null,
                    'longitude' => $attendance->check_out_longitude ? (float) $attendance->check_out_longitude : null,
                ],
                'gps_accuracy_meters' => $attendance->gps_accuracy_meters ? (float) $attendance->gps_accuracy_meters : null,
                'duration_minutes' => $durationData['minutes'],
                'duration_hours' => $durationData['hours'],
                'duration_formatted' => $durationData['formatted'],
                'is_in_progress' => $durationData['is_in_progress'],
                'distance_meters' => $distance,
                'distance_formatted' => $distance !== null ? ($distance > 1000 ? round($distance / 1000, 2) . ' km' : round($distance) . ' meters') : null,
                'status' => $attendance->status ?? 'pending',
                'attendance_type' => $attendance->attendance_type ?? 'present',
                'notes' => $attendance->notes,
                'reviewed_by' => $attendance->reviewedBy?->name,
                'reviewed_at' => $attendance->reviewed_at?->format('d M Y, h:i A'),
                'review_notes' => $attendance->review_notes,
            ],
        ]);
    }

    /**
     * Approve or reject an attendance log with review notes.
     */
    public function review(AttendanceRecord $attendance, Request $request): RedirectResponse
    {
        $actor = $this->constructionActor();
        $accessibleProjectIds = $this->getAccessibleProjectIds($actor);

        if (!$accessibleProjectIds->contains($attendance->project_id)) {
            abort(403, 'Unauthorized access to project attendance.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected,pending'],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $reviewerId = $this->resolveActorMemberId($actor);

        $attendance->update([
            'status' => $validated['status'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by_member_id' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', "Attendance record updated to {$validated['status']}.");
    }

    /**
     * Export attendance logs as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $actor = $this->constructionActor();
        $isProjectOwner = $this->isProjectOwner($actor);
        $accessibleProjectIds = $this->getAccessibleProjectIds($actor);

        $recordsQuery = AttendanceRecord::with(['project.company', 'member.employee'])
            ->whereIn('project_id', $accessibleProjectIds);

        if ($request->filled('project_id')) {
            $recordsQuery->where('project_id', (int) $request->input('project_id'));
        }

        if ($request->filled('date') && $request->input('date') !== 'all') {
            $date = $request->input('date');
            if ($date === 'today') {
                $recordsQuery->whereDate('attendance_date', Carbon::today());
            } else {
                $recordsQuery->whereDate('attendance_date', $date);
            }
        }

        $records = $recordsQuery->latest('attendance_date')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance-records-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Project Code',
                'Project Name',
                'Employee Code',
                'Employee Name',
                'Date',
                'Check-In Time',
                'Check-In Lat',
                'Check-In Long',
                'Check-Out Time',
                'Check-Out Lat',
                'Check-Out Long',
                'Shift Duration',
                'Status',
                'Notes',
            ]);

            foreach ($records as $record) {
                $duration = $this->calculateDuration($record->check_in_at, $record->check_out_at);
                fputcsv($handle, [
                    $record->id,
                    $record->project?->project_code ?? '',
                    $record->project?->name ?? '',
                    $record->member?->employee?->employee_id ?? ('EMP-' . $record->member_id),
                    $record->member?->name ?? '',
                    $record->attendance_date?->format('Y-m-d') ?? '',
                    $record->check_in_at?->format('Y-m-d H:i:s') ?? '',
                    $record->check_in_latitude ?? '',
                    $record->check_in_longitude ?? '',
                    $record->check_out_at?->format('Y-m-d H:i:s') ?? '',
                    $record->check_out_latitude ?? '',
                    $record->check_out_longitude ?? '',
                    $duration['formatted'],
                    $record->status ?? '',
                    $record->notes ?? '',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Compute shift duration between check-in and check-out.
     */
    protected function calculateDuration(?Carbon $checkIn, ?Carbon $checkOut): array
    {
        if (!$checkIn) {
            return [
                'minutes' => 0,
                'hours' => 0.0,
                'formatted' => '0h 0m',
                'is_in_progress' => false,
            ];
        }

        $end = $checkOut ?: Carbon::now();
        $minutes = (int) max(0, $checkIn->diffInMinutes($end));
        $hours = (int) floor($minutes / 60);
        $remMinutes = $minutes % 60;

        return [
            'minutes' => $minutes,
            'hours' => round($minutes / 60, 2),
            'formatted' => "{$hours}h {$remMinutes}m",
            'is_in_progress' => empty($checkOut),
        ];
    }

    /**
     * Compute Haversine distance in meters.
     */
    protected function haversineDistance(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return null;
        }

        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}

