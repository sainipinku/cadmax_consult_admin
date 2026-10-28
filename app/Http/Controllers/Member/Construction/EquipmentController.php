<?php

namespace App\Http\Controllers\Member\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\ConstructionEquipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentUsageLog;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\Member;
use App\Services\Construction\ConstructionEquipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentController extends Controller
{
    use ResolvesConstructionActor;

    public function index(ConstructionEquipmentService $equipmentService): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = ProjectTeamMember::where('member_id', $actor?->getKey())
            ->where('status', 'active')
            ->pluck('project_id');

        $membersQuery = Member::where('status', 1);
        $companyId = $this->getAccessibleCompanyId($actor);
        if ($companyId) {
            $companySuperAdminIds = \App\Models\SuperAdmin::where('company_id', $companyId)->pluck('id')->toArray();
            $membersQuery->where(function ($q) use ($companyId, $companySuperAdminIds, $actor) {
                if (!empty($companySuperAdminIds)) {
                    $q->whereIn('created_by', $companySuperAdminIds);
                }
                if ($actor?->getKey()) {
                    $q->orWhere('created_by', $actor->getKey());
                }
                if ($actor instanceof Member && $actor->created_by) {
                    $q->orWhere('created_by', $actor->created_by);
                }
            });
        }
        $members = $membersQuery
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'departments', 'designation', 'roles']);

        $mainEquipmentsCollection = \App\Models\Equipment::query()
            ->with(['category', 'assignedProject', 'assignedEmployee.member'])
            ->forActor($actor)
            ->latest()
            ->get();

        $mainEquipments = $mainEquipmentsCollection->map(function ($e) {
            return [
                'id' => $e->id,
                'project_id' => $e->assigned_project_id,
                'equipment_code' => $e->equipment_id,
                'name' => $e->equipment_name,
                'equipment_type' => $e->category?->category_name ?? $e->brand ?? 'Equipment',
                'brand' => $e->brand,
                'model' => $e->model,
                'serial_number' => $e->serial_number,
                'status' => (int) $e->status === 0 ? 'active' : ((int) $e->status === 1 ? 'allocated' : 'maintenance'),
                'source' => 'main',
                'photo_url' => $e->photo_url,
                'assigned_employee' => $e->assignedEmployee?->member?->name ?? $e->assignedEmployee?->employee_id,
                'assigned_project' => $e->assignedProject?->name,
                'assigned_date' => $e->assigned_date?->toDateString(),
            ];
        });

        $constructionEquipments = ConstructionEquipment::with('project')
            ->whereIn('project_id', $projectIds)
            ->latest()
            ->get()
            ->map(function ($e) {
                return [
                    'id' => $e->id,
                    'project_id' => $e->project_id,
                    'equipment_code' => $e->equipment_code,
                    'name' => $e->name,
                    'equipment_type' => $e->equipment_type,
                    'brand' => null,
                    'model' => null,
                    'serial_number' => $e->serial_number,
                    'status' => $e->status,
                    'source' => 'construction',
                ];
            });

        $allEquipments = $mainEquipments->concat($constructionEquipments)->values();

        $constructionAllocations = EquipmentAllocation::with(['project', 'equipment', 'assignedTo'])
            ->whereIn('project_id', $projectIds)
            ->latest()
            ->take(100)
            ->get();

        $existingAllocCodes = $constructionAllocations
            ->map(fn ($a) => strtolower(trim((string) ($a->equipment?->equipment_code ?? ''))))
            ->filter()
            ->all();

        $mainAllocations = $mainEquipmentsCollection->filter(function ($e) use ($existingAllocCodes) {
            $isAssigned = (int) $e->status === 1 || !empty($e->assigned_employee_id) || !empty($e->assigned_project_id);
            if (!$isAssigned) {
                return false;
            }
            $code = strtolower(trim((string) $e->equipment_id));
            return !in_array($code, $existingAllocCodes, true);
        })->map(function ($e) {
            $memberName = $e->assignedEmployee?->member?->name ?? $e->assignedEmployee?->employee_id;
            return [
                'id' => 'main_' . $e->id,
                'project_id' => $e->assigned_project_id,
                'equipment_id' => $e->id,
                'equipment' => [
                    'id' => $e->id,
                    'name' => $e->equipment_name,
                    'equipment_code' => $e->equipment_id,
                    'equipment_type' => $e->category?->category_name ?? $e->brand ?? 'Equipment',
                ],
                'project' => $e->assignedProject ? [
                    'id' => $e->assignedProject->id,
                    'project_code' => $e->assignedProject->project_code,
                    'name' => $e->assignedProject->name,
                ] : null,
                'assigned_to' => $e->assignedEmployee?->member ? [
                    'id' => $e->assignedEmployee->member->id,
                    'name' => $e->assignedEmployee->member->name,
                    'email' => $e->assignedEmployee->member->email ?? '',
                ] : ($memberName ? [
                    'id' => $e->assigned_employee_id,
                    'name' => $memberName,
                    'email' => '',
                ] : null),
                'assigned_from' => $e->assigned_date ? $e->assigned_date->toIso8601String() : now()->toIso8601String(),
                'assigned_to' => null,
                'hours' => null,
                'status' => 'active',
                'notes' => $memberName ? 'Assigned to ' . $memberName : 'Assigned in Equipment Master',
                'source' => 'main',
            ];
        });

        $allAllocations = $constructionAllocations->concat($mainAllocations)->values();

        return Inertia::render('Member/Construction/Equipment/Index', [
            'projects' => Project::whereIn('id', $projectIds)->orderByDesc('id')->get(['id', 'project_code', 'name']),
            'equipments' => $allEquipments,
            'allocations' => $allAllocations,
            'usageLogs' => EquipmentUsageLog::with(['project', 'equipment', 'member'])
                ->whereIn('project_id', $projectIds)
                ->latest()
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

    public function returnAllocation(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $allocationId = (string) $request->input('allocation_id');

        if (str_starts_with($allocationId, 'main_')) {
            $mainId = (int) substr($allocationId, 5);
            $equipment = \App\Models\Equipment::forActor($actor)->findOrFail($mainId);
            $equipment->update([
                'status' => 0,
                'assigned_project_id' => null,
                'assigned_employee_id' => null,
                'assigned_date' => null,
            ]);

            return back()->with('success', 'Equipment unassigned successfully.');
        }

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
