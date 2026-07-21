<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Project;
use App\Models\Construction\Vehicle;
use App\Models\Construction\VehicleAssignment;
use App\Models\Construction\VehicleLocationPing;
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
        return Inertia::render('SuperAdmin/Construction/Vehicles/Index', [
            'projects' => Project::orderByDesc('id')->get(['id', 'project_code', 'name']),
            'vehicles' => Vehicle::with('project')->latest()->take(60)->get(),
            'assignments' => VehicleAssignment::with(['project', 'vehicle', 'driver'])
                ->latest()
                ->take(60)
                ->get(),
            'pings' => VehicleLocationPing::with(['project', 'vehicle', 'reportedBy'])
                ->latest('recorded_at')
                ->take(120)
                ->get(),
        ]);
    }

    public function storeVehicle(Request $request, ConstructionFleetService $fleetService): RedirectResponse
    {
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

        $project = Project::findOrFail($validated['project_id']);
        $fleetService->createVehicle($project, $validated, $actor, $request);

        return back()->with('success', 'Vehicle saved successfully.');
    }

    public function storeAssignment(Request $request, ConstructionFleetService $fleetService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'vehicle_id' => ['required', 'exists:construction_vehicles,id'],
            'driver_member_id' => ['nullable', 'exists:members,id'],
            'assigned_from' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $fleetService->assignVehicle($project, $validated, $actor, $request);

        return back()->with('success', 'Vehicle assignment saved successfully.');
    }

    public function storePing(Request $request, ConstructionFleetService $fleetService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'vehicle_id' => ['required', 'exists:construction_vehicles,id'],
            'reported_by_member_id' => ['nullable', 'exists:members,id'],
            'recorded_at' => ['nullable', 'date'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'speed_kmph' => ['nullable', 'numeric', 'min:0'],
            'heading_degrees' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $fleetService->recordLocationPing($project, $validated, $actor, $request);

        return back()->with('success', 'Vehicle location ping saved successfully.');
    }
}

