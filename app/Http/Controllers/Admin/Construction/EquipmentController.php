<?php

namespace App\Http\Controllers\Admin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Equipment;
use App\Models\Construction\EquipmentAllocation;
use App\Models\Construction\EquipmentUsageLog;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Member;
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
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = ProjectTeamMember::where('member_id', $actor?->getKey())
            ->where('status', 'active')
            ->pluck('project_id');

        return Inertia::render('Admin/Construction/Equipment/Index', [
            'projects' => Project::whereIn('id', $projectIds)->orderByDesc('id')->get(['id', 'project_code', 'name']),
            'equipments' => Equipment::with('project')->whereIn('project_id', $projectIds)->latest()->take(80)->get(),
            'allocations' => EquipmentAllocation::with(['project', 'equipment', 'assignedTo'])
                ->whereIn('project_id', $projectIds)
                ->latest()
                ->take(80)
                ->get(),
            'usageLogs' => EquipmentUsageLog::with(['project', 'equipment', 'member'])
                ->whereIn('project_id', $projectIds)
                ->latest()
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

    public function storeEquipment(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'equipment_code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'equipment_type' => ['nullable', 'string', 'max:80'],
            'serial_number' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $equipmentService->createEquipment($project, $validated, $actor, $request);

        return back()->with('success', 'Equipment saved successfully.');
    }

    public function storeAllocation(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        /** @var Member|null $actor */
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

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $equipmentService->allocateEquipment($project, $validated, $actor, $request);

        return back()->with('success', 'Equipment allocation saved successfully.');
    }

    public function returnAllocation(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'allocation_id' => ['required', 'exists:construction_equipment_allocations,id'],
            'returned_at' => ['nullable', 'date'],
            'return_latitude' => ['nullable', 'numeric'],
            'return_longitude' => ['nullable', 'numeric'],
            'return_gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $equipmentService->returnEquipment($project, $validated, $actor, $request);

        return back()->with('success', 'Equipment returned successfully.');
    }

    public function storeUsage(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'equipment_id' => ['required', 'exists:construction_equipments,id'],
            'log_date' => ['required', 'date'],
            'hours_used' => ['required', 'numeric', 'min:0.01'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $equipmentService->recordUsage($project, $validated, $actor, $request);

        return back()->with('success', 'Equipment usage saved successfully.');
    }
}

