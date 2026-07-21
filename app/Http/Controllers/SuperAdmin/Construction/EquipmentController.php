<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Equipment;
use App\Models\Construction\EquipmentAllocation;
use App\Models\Construction\EquipmentUsageLog;
use App\Models\Construction\Project;
use App\Services\Construction\ConstructionEquipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Construction/Equipment/Index', [
            'projects' => Project::orderByDesc('id')->get(['id', 'project_code', 'name']),
            'equipments' => Equipment::with('project')->latest()->take(80)->get(),
            'allocations' => EquipmentAllocation::with(['project', 'equipment', 'assignedTo'])
                ->latest()
                ->take(80)
                ->get(),
            'usageLogs' => EquipmentUsageLog::with(['project', 'equipment', 'member'])
                ->latest()
                ->take(120)
                ->get(),
        ]);
    }

    public function storeEquipment(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'equipment_code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'equipment_type' => ['nullable', 'string', 'max:80'],
            'serial_number' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $equipmentService->createEquipment($project, $validated, $actor, $request);

        return back()->with('success', 'Equipment saved successfully.');
    }

    public function storeAllocation(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'equipment_id' => ['required', 'exists:construction_equipments,id'],
            'assigned_to_member_id' => ['nullable', 'exists:members,id'],
            'allocated_at' => ['nullable', 'date'],
            'allocate_latitude' => ['nullable', 'numeric'],
            'allocate_longitude' => ['nullable', 'numeric'],
            'allocate_gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $equipmentService->allocateEquipment($project, $validated, $actor, $request);

        return back()->with('success', 'Equipment allocation saved successfully.');
    }

    public function returnAllocation(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'allocation_id' => ['required', 'exists:construction_equipment_allocations,id'],
            'returned_at' => ['nullable', 'date'],
            'return_latitude' => ['nullable', 'numeric'],
            'return_longitude' => ['nullable', 'numeric'],
            'return_gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $equipmentService->returnEquipment($project, $validated, $actor, $request);

        return back()->with('success', 'Equipment returned successfully.');
    }

    public function storeUsage(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'equipment_id' => ['required', 'exists:construction_equipments,id'],
            'member_id' => ['nullable', 'exists:members,id'],
            'log_date' => ['required', 'date'],
            'hours_used' => ['required', 'numeric', 'min:0.01'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $equipmentService->recordUsage($project, $validated, $actor, $request);

        return back()->with('success', 'Equipment usage saved successfully.');
    }
}

