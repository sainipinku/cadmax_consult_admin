<?php

namespace App\Http\Controllers\Admin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\ConstructionVehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLocationPing;
use App\Models\Member;
use App\Services\Construction\ConstructionFleetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehiclesController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = ProjectTeamMember::where('member_id', $actor?->getKey())
            ->where('status', 'active')
            ->pluck('project_id');

        $driverRoleIds = \App\Models\ConstructionRole::where('slug', 'vehicle_driver')
            ->orWhere('name', 'Driver')
            ->pluck('id');

        $driverMemberIds = \App\Models\MemberRoleAssignment::whereIn('role_id', $driverRoleIds)
            ->where('status', 1)
            ->pluck('member_id')
            ->toArray();

        $driverDesignationIds = \App\Models\Designation::where('name', 'like', '%Driver%')->pluck('id')->toArray();

        $members = Member::where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'departments', 'designation', 'roles']);

        $members->transform(function ($member) use ($driverMemberIds, $driverDesignationIds) {
            $desigStr = '';
            if (!empty($member->designation)) {
                if (is_array($member->designation)) {
                    $desigValues = array_values($member->designation);
                    if (isset($desigValues[0]) && is_numeric($desigValues[0])) {
                        $desigStr = \App\Models\Designation::whereIn('id', $desigValues)->pluck('name')->implode(', ');
                    } else {
                        $desigStr = implode(', ', $desigValues);
                    }
                } else {
                    $desigStr = (string)$member->designation;
                }
            }
            $member->designation_text = $desigStr;

            $isDriver = in_array($member->id, $driverMemberIds);
            if (!$isDriver && !empty($member->roles) && is_array($member->roles)) {
                foreach ($member->roles as $r) {
                    if (is_string($r) && in_array(strtolower($r), ['vehicle_driver', 'driver'])) {
                        $isDriver = true;
                        break;
                    }
                }
            }
            if (!$isDriver && !empty($member->designation)) {
                if (is_array($member->designation)) {
                    foreach ($member->designation as $d) {
                        if (in_array($d, $driverDesignationIds) || (is_string($d) && str_contains(strtolower((string)$d), 'driver'))) {
                            $isDriver = true;
                            break;
                        }
                    }
                } else if (is_string($member->designation) && str_contains(strtolower($member->designation), 'driver')) {
                    $isDriver = true;
                }
            }
            $member->is_driver = $isDriver;

            return $member;
        });

        $mainVehicles = \App\Models\Vehicle::latest()->get()->map(function ($v) {
            return [
                'id' => $v->id,
                'project_id' => null,
                'vehicle_code' => $v->vehicle_id,
                'registration_number' => $v->vehicle_number,
                'vehicle_name' => $v->vehicle_name,
                'vehicle_type' => $v->vehicle_type,
                'source' => 'main',
            ];
        });

        $constructionVehicles = ConstructionVehicle::with('project')->whereIn('project_id', $projectIds)->latest()->get()->map(function ($v) {
            return [
                'id' => $v->id,
                'project_id' => $v->project_id,
                'vehicle_code' => $v->vehicle_code,
                'registration_number' => $v->registration_number,
                'vehicle_name' => $v->make ? ($v->make . ' ' . $v->model) : null,
                'vehicle_type' => $v->vehicle_type,
                'source' => 'construction',
            ];
        });

        $allVehicles = $mainVehicles->concat($constructionVehicles)->values();

        return Inertia::render('Admin/Construction/Vehicles/Index', [
            'projects' => Project::whereIn('id', $projectIds)->orderByDesc('id')->get(['id', 'project_code', 'name']),
            'vehicles' => $allVehicles,
            'assignments' => VehicleAssignment::with(['project', 'vehicle', 'driver'])
                ->whereIn('project_id', $projectIds)
                ->latest()
                ->take(60)
                ->get(),
            'pings' => VehicleLocationPing::with(['project', 'vehicle', 'reportedBy'])
                ->whereIn('project_id', $projectIds)
                ->latest('recorded_at')
                ->take(120)
                ->get(),
            'members' => $members,
        ]);
    }

    private function ensureProjectAccess(int $projectId, ?Member $actor): void
    {
        abort_unless(
            $actor && ProjectTeamMember::where('project_id', $projectId)
                ->where('member_id', $actor->getKey())
                ->where('status', 'active')
                ->exists(),
            403
        );
    }

    public function storeVehicle(Request $request, ConstructionFleetService $fleetService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'vehicle_code' => ['nullable', 'string', 'max:30'],
            'registration_number' => ['required', 'string', 'max:30'],
            'vehicle_type' => ['nullable', 'string', 'max:50'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $fleetService->createVehicle($project, $validated, $actor, $request);

        return back()->with('success', 'Vehicle saved successfully.');
    }

    public function storeAssignment(Request $request, ConstructionFleetService $fleetService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'vehicle_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $vStr = (string) $value;
                    if (str_starts_with($vStr, 'main_')) {
                        $id = (int) substr($vStr, 5);
                        if (!\App\Models\Vehicle::where('id', $id)->exists()) {
                            $fail('The selected main vehicle is invalid.');
                        }
                    } elseif (str_starts_with($vStr, 'construction_') || str_starts_with($vStr, 'cv_')) {
                        $id = (int) preg_replace('/^\D+/', '', $vStr);
                        if (!\App\Models\ConstructionVehicle::where('id', $id)->exists()) {
                            $fail('The selected project vehicle is invalid.');
                        }
                    } else {
                        $id = (int) $vStr;
                        $existsInVehicles = \App\Models\Vehicle::where('id', $id)->exists();
                        $existsInConstruction = \App\Models\ConstructionVehicle::where('id', $id)->exists();
                        if (!$existsInVehicles && !$existsInConstruction) {
                            $fail('The selected vehicle id is invalid.');
                        }
                    }
                },
            ],
            'driver_member_id' => ['nullable', 'exists:members,id'],
            'assigned_from' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $fleetService->assignVehicle($project, $validated, $actor, $request);

        return back()->with('success', 'Vehicle assignment saved successfully.');
    }

    public function storePing(Request $request, ConstructionFleetService $fleetService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'vehicle_id' => ['required', 'exists:construction_vehicles,id'],
            'recorded_at' => ['nullable', 'date'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'speed_kmph' => ['nullable', 'numeric', 'min:0'],
            'heading_degrees' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $fleetService->recordLocationPing($project, $validated, $actor, $request);

        return back()->with('success', 'Vehicle location ping saved successfully.');
    }
}

