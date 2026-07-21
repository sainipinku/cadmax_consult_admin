<?php

namespace App\Http\Controllers\Member\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Construction\Vehicle;
use App\Models\Construction\VehicleAssignment;
use App\Models\Construction\VehicleLocationPing;
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

        return Inertia::render('Member/Construction/Vehicles/Index', [
            'projects' => Project::whereIn('id', $projectIds)->orderByDesc('id')->get(['id', 'project_code', 'name']),
            'vehicles' => Vehicle::with('project')->whereIn('project_id', $projectIds)->latest()->take(60)->get(),
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

