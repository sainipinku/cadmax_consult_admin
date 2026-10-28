<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\ConstructionEquipment;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentUsageLog;
use App\Models\Member;
use App\Models\Project;
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
        $actor = $this->constructionActor();
        $projectIds = $this->getAccessibleProjectIds($actor);

        $membersQuery = Member::where('status', 1);
        if ($actor instanceof \App\Models\SuperAdmin) {
            if ($actor->company_id) {
                $companySuperAdminIds = \App\Models\SuperAdmin::where('company_id', $actor->company_id)->pluck('id')->toArray();
                $membersQuery->where(function ($q) use ($actor, $companySuperAdminIds) {
                    if (!empty($companySuperAdminIds)) {
                        $q->whereIn('created_by', $companySuperAdminIds);
                    }
                    $q->orWhere('created_by', $actor->id);
                    if ($actor->company) {
                        $q->orWhere('company_name', $actor->company->name);
                    }
                });
            } else {
                $membersQuery->where('created_by', $actor->id);
            }
        }
        $members = $membersQuery
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'departments', 'designation', 'roles']);

        $mainEquipmentsCollection = Equipment::query()
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
            ->forActor($actor)
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

        return Inertia::render('SuperAdmin/Construction/Equipment/Index', [
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

    public function storeEquipment(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        $actor = $this->constructionActor();
        $projectIds = $this->getAccessibleProjectIds($actor);

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'equipment_code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'equipment_type' => ['nullable', 'string', 'max:80'],
            'serial_number' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $project = Project::whereIn('id', $projectIds)->findOrFail($validated['project_id']);
        $equipmentService->createEquipment($project, $validated, $actor, $request);

        return back()->with('success', 'Equipment saved successfully.');
    }

    public function storeAllocation(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        $actor = $this->constructionActor();
        $projectIds = $this->getAccessibleProjectIds($actor);

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'equipment_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $vStr = (string) $value;
                    if (str_starts_with($vStr, 'main_')) {
                        $id = (int) substr($vStr, 5);
                        if (!Equipment::where('id', $id)->exists()) {
                            $fail('The selected equipment is invalid.');
                        }
                    } elseif (str_starts_with($vStr, 'construction_') || str_starts_with($vStr, 'ceq_')) {
                        $id = (int) preg_replace('/^\D+/', '', $vStr);
                        if (!ConstructionEquipment::withTrashed()->where('id', $id)->exists()) {
                            $fail('The selected project equipment is invalid.');
                        }
                    } else {
                        $id = (int) $vStr;
                        $existsInMain = Equipment::where('id', $id)->exists();
                        $existsInConstruction = ConstructionEquipment::withTrashed()->where('id', $id)->exists();
                        if (!$existsInMain && !$existsInConstruction) {
                            $fail('The selected equipment id is invalid.');
                        }
                    }
                },
            ],
            'assigned_to_member_id' => ['nullable', 'exists:members,id'],
            'assigned_from' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'date'],
            'allocated_at' => ['nullable', 'date'],
            'hours' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'allocate_latitude' => ['nullable', 'numeric'],
            'allocate_longitude' => ['nullable', 'numeric'],
            'allocate_gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        $project = Project::whereIn('id', $projectIds)->findOrFail($validated['project_id']);
        $equipmentService->allocateEquipment($project, $validated, $actor, $request);

        return back()->with('success', 'Equipment allocation saved successfully.');
    }

    public function returnAllocation(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        $actor = $this->constructionActor();
        $allocationId = (string) $request->input('allocation_id');

        if (str_starts_with($allocationId, 'main_')) {
            $mainId = (int) substr($allocationId, 5);
            $equipment = Equipment::forActor($actor)->findOrFail($mainId);
            $equipment->update([
                'status' => 0,
                'assigned_project_id' => null,
                'assigned_employee_id' => null,
                'assigned_date' => null,
            ]);

            return back()->with('success', 'Equipment unassigned successfully.');
        }

        $projectIds = $this->getAccessibleProjectIds($actor);
        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'allocation_id' => ['required', 'exists:construction_equipment_allocations,id'],
            'returned_at' => ['nullable', 'date'],
            'return_latitude' => ['nullable', 'numeric'],
            'return_longitude' => ['nullable', 'numeric'],
            'return_gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        $project = Project::whereIn('id', $projectIds)->findOrFail($validated['project_id']);
        $equipmentService->returnEquipment($project, $validated, $actor, $request);

        return back()->with('success', 'Equipment returned successfully.');
    }

    public function storeUsage(Request $request, ConstructionEquipmentService $equipmentService): RedirectResponse
    {
        $actor = $this->constructionActor();
        $projectIds = $this->getAccessibleProjectIds($actor);

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

        $project = Project::whereIn('id', $projectIds)->findOrFail($validated['project_id']);
        $equipmentService->recordUsage($project, $validated, $actor, $request);

        return back()->with('success', 'Equipment usage saved successfully.');
    }
}

