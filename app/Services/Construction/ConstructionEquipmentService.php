<?php

namespace App\Services\Construction;

use App\Models\ConstructionEquipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentUsageLog;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConstructionEquipmentService
{
    public function __construct(
        private readonly ConstructionActivityService $activityService
    ) {
    }

    public function createEquipment(Project $project, array $validated, ?Model $actor, ?Request $request = null): ConstructionEquipment
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $nextId = (ConstructionEquipment::max('id') ?? 0) + 1;
            $equipmentCode = $validated['equipment_code'] ?? ('EQP-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT));

            $equipment = ConstructionEquipment::create([
                'project_id' => $project->id,
                'equipment_code' => $equipmentCode,
                'name' => $validated['name'],
                'equipment_type' => $validated['equipment_type'] ?? null,
                'serial_number' => $validated['serial_number'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'created_by_type' => $actor ? $actor::class : null,
                'created_by_id' => $actor?->getKey(),
            ]);

            $this->activityService->log(
                module: 'equipment',
                action: 'created',
                actor: $actor,
                reference: $equipment,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'equipment_code' => $equipment->equipment_code,
                    'name' => $equipment->name,
                ],
                request: $request
            );

            return $equipment;
        });
    }

    public function resolveOrCreateFromMainEquipment(
        Project $project,
        \App\Models\Equipment $mainEquipment,
        ?Model $actor
    ): ConstructionEquipment {
        $desiredCode = $mainEquipment->equipment_id ?: ('EQP-' . str_pad((string) $mainEquipment->id, 5, '0', STR_PAD_LEFT));

        /** @var ConstructionEquipment|null $equipment */
        $equipment = ConstructionEquipment::withTrashed()
            ->where(function ($query) use ($desiredCode, $mainEquipment) {
                $query->where('equipment_code', $desiredCode);
                if (!empty($mainEquipment->serial_number)) {
                    $query->orWhere('serial_number', $mainEquipment->serial_number);
                }
            })
            ->first();

        if ($equipment) {
            if ($equipment->trashed()) {
                $equipment->restore();
            }
            $equipment->project_id = $project->id;
            $equipment->name = $mainEquipment->equipment_name;
            if (!empty($mainEquipment->category?->category_name)) {
                $equipment->equipment_type = $mainEquipment->category->category_name;
            }
            $equipment->status = 'active';
            $equipment->save();

            return $equipment;
        }

        return ConstructionEquipment::create([
            'project_id' => $project->id,
            'equipment_code' => $desiredCode,
            'name' => $mainEquipment->equipment_name,
            'equipment_type' => $mainEquipment->category?->category_name ?? $mainEquipment->brand ?? 'Equipment',
            'serial_number' => $mainEquipment->serial_number,
            'status' => 'active',
            'created_by_type' => $actor ? $actor::class : null,
            'created_by_id' => $actor?->getKey(),
        ]);
    }

    public function includeMasterEquipmentAssignments(
        Collection $allocations,
        iterable $projectIds,
        ?Model $actor
    ): Collection {
        $allocations = $allocations->map(function ($allocation) {
            if ($allocation instanceof EquipmentAllocation) {
                $allocation->setAttribute(
                    'scheduled_return_at',
                    $allocation->getRawOriginal('assigned_to')
                );
                $allocation->setAttribute('assigned_member', $allocation->assignedTo);
            }

            return $allocation;
        });

        $projectIds = collect($projectIds)->map(fn ($id) => (int) $id)->all();
        if (empty($projectIds)) {
            return $allocations;
        }

        $activeEquipmentCodes = EquipmentAllocation::query()
            ->with('equipment:id,equipment_code')
            ->whereIn('project_id', $projectIds)
            ->where('status', 'active')
            ->get()
            ->pluck('equipment.equipment_code')
            ->filter()
            ->map(fn ($code) => strtolower(trim($code)))
            ->flip();

        $projects = Project::whereIn('id', $projectIds)
            ->get(['id', 'project_code', 'name'])
            ->keyBy('id');

        $legacyEquipmentQuery = \App\Models\Equipment::query()
            ->with('assignedEmployee.member:id,name,email');
        if (!($actor instanceof \App\Models\SuperAdmin && (int) $actor->id === 1 && !$actor->company_id)) {
            $legacyEquipmentQuery->forActor($actor);
        }

        $legacyAssignments = $legacyEquipmentQuery
            ->where('status', 1)
            ->whereNotNull('assigned_project_id')
            ->whereIn('assigned_project_id', $projectIds)
            ->get()
            ->filter(function ($equipment) use ($activeEquipmentCodes) {
                return !$activeEquipmentCodes->has(strtolower(trim($equipment->equipment_id)));
            })
            ->map(function ($equipment) use ($projects) {
                $project = $projects->get($equipment->assigned_project_id);
                $member = $equipment->assignedEmployee?->member;

                return [
                    'id' => 'master-' . $equipment->id,
                    'project_id' => $equipment->assigned_project_id,
                    'equipment_id' => 'main-' . $equipment->id,
                    'assigned_to_member_id' => $member?->id,
                    'allocated_at' => $equipment->assigned_date ?? $equipment->created_at,
                    'assigned_from' => $equipment->assigned_date ?? $equipment->created_at,
                    'scheduled_return_at' => null,
                    'hours' => null,
                    'status' => 'active',
                    'notes' => 'Assigned from Equipment Master',
                    'legacy_assignment' => true,
                    'equipment' => [
                        'id' => $equipment->id,
                        'equipment_code' => $equipment->equipment_id,
                        'name' => $equipment->equipment_name,
                    ],
                    'project' => $project,
                    'assigned_member' => $member,
                ];
            });

        return $allocations->concat($legacyAssignments)->values();
    }

    public function expirePastAllocations(): int
    {
        $expiredAllocations = EquipmentAllocation::query()
            ->where('status', 'active')
            ->whereNotNull('assigned_to')
            ->where('assigned_to', '<', now())
            ->get();

        $count = 0;
        foreach ($expiredAllocations as $alloc) {
            $alloc->update([
                'status' => 'returned',
                'returned_at' => $alloc->assigned_to,
            ]);
            $count++;

            if ($alloc->equipment) {
                $mainEq = \App\Models\Equipment::where('equipment_id', $alloc->equipment->equipment_code)
                    ->orWhere(function ($q) use ($alloc) {
                        if (!empty($alloc->equipment->serial_number)) {
                            $q->where('serial_number', $alloc->equipment->serial_number);
                        }
                    })
                    ->first();
                if ($mainEq) {
                    $mainEq->update([
                        'status' => 0,
                        'assigned_project_id' => null,
                        'assigned_employee_id' => null,
                        'assigned_date' => null,
                    ]);
                }
            }
        }

        return $count;
    }

    public function allocateEquipment(Project $project, array $validated, ?Model $actor, ?Request $request = null): EquipmentAllocation
    {
        $this->expirePastAllocations();

        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $rawEquipmentId = (string) $validated['equipment_id'];
            /** @var ConstructionEquipment|null $equipment */
            $equipment = null;
            /** @var \App\Models\Equipment|null $mainEquipment */
            $mainEquipment = null;

            if (str_starts_with($rawEquipmentId, 'main_')) {
                $mainId = (int) substr($rawEquipmentId, 5);
                $mainEquipment = \App\Models\Equipment::find($mainId);
                if ($mainEquipment) {
                    $equipment = $this->resolveOrCreateFromMainEquipment($project, $mainEquipment, $actor);
                }
            } elseif (str_starts_with($rawEquipmentId, 'construction_') || str_starts_with($rawEquipmentId, 'ceq_')) {
                $ceqId = (int) preg_replace('/^\D+/', '', $rawEquipmentId);
                $equipment = ConstructionEquipment::withTrashed()->where('project_id', $project->id)->find($ceqId);
                if ($equipment && $equipment->trashed()) {
                    $equipment->restore();
                }
            } else {
                $id = (int) $rawEquipmentId;
                $equipment = ConstructionEquipment::where('project_id', $project->id)->find($id);
                if (!$equipment) {
                    $mainEquipment = \App\Models\Equipment::find($id);
                    if ($mainEquipment) {
                        $equipment = $this->resolveOrCreateFromMainEquipment($project, $mainEquipment, $actor);
                    }
                }
            }

            if (!$equipment) {
                throw ValidationException::withMessages([
                    'equipment_id' => 'The selected equipment does not belong to the chosen project.',
                ]);
            }

            $assignedFrom = isset($validated['assigned_from']) && !empty($validated['assigned_from'])
                ? \Illuminate\Support\Carbon::parse($validated['assigned_from'])
                : (isset($validated['allocated_at']) && !empty($validated['allocated_at']) ? \Illuminate\Support\Carbon::parse($validated['allocated_at']) : now());
            $assignedTo = isset($validated['assigned_to']) && !empty($validated['assigned_to'])
                ? \Illuminate\Support\Carbon::parse($validated['assigned_to'])
                : null;

            // Check if equipment has an active allocation overlapping with [assigned_from, assigned_to]
            $equipmentOverlap = EquipmentAllocation::query()
                ->where('equipment_id', $equipment->id)
                ->where('status', 'active')
                ->where(function ($q) use ($assignedFrom, $assignedTo) {
                    if ($assignedTo) {
                        $q->where(function ($sub) use ($assignedTo) {
                            $sub->whereNull('assigned_from')
                                ->orWhere('assigned_from', '<', $assignedTo);
                        })->where(function ($sub) use ($assignedFrom) {
                            $sub->whereNull('assigned_to')
                                ->orWhere('assigned_to', '>', $assignedFrom);
                        });
                    } else {
                        $q->whereNull('assigned_to')
                            ->orWhere('assigned_to', '>', $assignedFrom);
                    }
                })
                ->first();

            if ($equipmentOverlap) {
                $startFormatted = $equipmentOverlap->assigned_from ? \Illuminate\Support\Carbon::parse($equipmentOverlap->assigned_from)->format('d/m/Y H:i') : 'Start';
                $endFormatted = $equipmentOverlap->assigned_to ? \Illuminate\Support\Carbon::parse($equipmentOverlap->assigned_to)->format('d/m/Y H:i') : 'Scheduled End';
                throw ValidationException::withMessages([
                    'equipment_id' => "This equipment is already allocated from {$startFormatted} to {$endFormatted}. Please choose a different date range or equipment.",
                ]);
            }

            $assignedToMemberId = isset($validated['assigned_to_member_id']) && !empty($validated['assigned_to_member_id'])
                ? (int) $validated['assigned_to_member_id']
                : null;
            if ($assignedToMemberId) {
                $this->ensureMemberIsOnProject($project, $assignedToMemberId, 'assigned_to_member_id');
            }

            $accuracy = isset($validated['allocate_gps_accuracy_meters']) ? (float) $validated['allocate_gps_accuracy_meters'] : null;
            $gpsVerified = $accuracy !== null && $accuracy > 0 && $accuracy <= 50;

            $assignedFrom = $validated['assigned_from'] ?? $validated['allocated_at'] ?? now();
            $assignedTo = $validated['assigned_to'] ?? null;
            $hours = isset($validated['hours']) && $validated['hours'] !== '' ? (float) $validated['hours'] : null;

            $allocation = EquipmentAllocation::create([
                'project_id' => $project->id,
                'equipment_id' => $equipment->id,
                'assigned_to_member_id' => $assignedToMemberId,
                'allocated_at' => $assignedFrom,
                'assigned_from' => $assignedFrom,
                'assigned_to' => $assignedTo,
                'hours' => $hours,
                'allocate_latitude' => $validated['allocate_latitude'] ?? null,
                'allocate_longitude' => $validated['allocate_longitude'] ?? null,
                'allocate_gps_accuracy_meters' => $accuracy,
                'allocate_gps_verified' => $gpsVerified,
                'status' => $validated['status'] ?? 'active',
                'notes' => $validated['notes'] ?? $validated['note'] ?? null,
                'allocated_by_type' => $actor ? $actor::class : null,
                'allocated_by_id' => $actor?->getKey(),
            ]);

            if ($mainEquipment) {
                $mainEquipment->update([
                    'status' => 1,
                    'assigned_project_id' => $project->id,
                    'assigned_date' => is_string($assignedFrom) ? substr($assignedFrom, 0, 10) : now()->toDateString(),
                ]);
            }

            $this->activityService->log(
                module: 'equipment_allocation',
                action: 'allocated',
                actor: $actor,
                reference: $allocation,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'equipment_id' => $equipment->id,
                    'assigned_to_member_id' => $assignedToMemberId,
                    'allocate_gps_verified' => $gpsVerified,
                ],
                request: $request
            );

            return $allocation->load(['equipment', 'assignedTo']);
        });
    }

    public function returnEquipment(Project $project, array $validated, ?Model $actor, ?Request $request = null): EquipmentAllocation
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $allocation = EquipmentAllocation::query()
                ->whereKey((int) $validated['allocation_id'])
                ->where('project_id', $project->id)
                ->first();

            if (!$allocation) {
                throw ValidationException::withMessages([
                    'allocation_id' => 'The selected allocation does not belong to the chosen project.',
                ]);
            }

            if ($allocation->status !== 'active') {
                throw ValidationException::withMessages([
                    'allocation_id' => 'Only active allocations can be returned.',
                ]);
            }

            $accuracy = isset($validated['return_gps_accuracy_meters']) ? (float) $validated['return_gps_accuracy_meters'] : null;
            $gpsVerified = $accuracy !== null && $accuracy > 0 && $accuracy <= 50;

            $allocation->forceFill([
                'returned_at' => $validated['returned_at'] ?? now(),
                'return_latitude' => $validated['return_latitude'] ?? null,
                'return_longitude' => $validated['return_longitude'] ?? null,
                'return_gps_accuracy_meters' => $accuracy,
                'return_gps_verified' => $gpsVerified,
                'status' => 'returned',
                'returned_by_type' => $actor ? $actor::class : null,
                'returned_by_id' => $actor?->getKey(),
            ])->save();

            // Also free up main equipment if matched
            if ($allocation->equipment) {
                $mainEq = \App\Models\Equipment::where('equipment_id', $allocation->equipment->equipment_code)
                    ->orWhere(function ($q) use ($allocation) {
                        if (!empty($allocation->equipment->serial_number)) {
                            $q->where('serial_number', $allocation->equipment->serial_number);
                        }
                    })
                    ->first();
                if ($mainEq) {
                    $mainEq->update([
                        'status' => 0,
                        'assigned_project_id' => null,
                        'assigned_employee_id' => null,
                        'assigned_date' => null,
                    ]);
                }
            }

            $this->activityService->log(
                module: 'equipment_allocation',
                action: 'returned',
                actor: $actor,
                reference: $allocation,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'equipment_id' => $allocation->equipment_id,
                    'return_gps_verified' => $gpsVerified,
                ],
                request: $request
            );

            return $allocation->load(['equipment', 'assignedTo']);
        });
    }

    public function recordUsage(Project $project, array $validated, ?Model $actor, ?Request $request = null): EquipmentUsageLog
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $equipment = ConstructionEquipment::query()
                ->whereKey((int) $validated['equipment_id'])
                ->where('project_id', $project->id)
                ->first();

            if (!$equipment) {
                throw ValidationException::withMessages([
                    'equipment_id' => 'The selected equipment does not belong to the chosen project.',
                ]);
            }

            $memberId = isset($validated['member_id'])
                ? (int) $validated['member_id']
                : ($actor instanceof Member ? (int) $actor->getKey() : null);

            if ($memberId) {
                $this->ensureMemberIsOnProject($project, $memberId, 'member_id');
            }

            $hours = (float) $validated['hours_used'];
            if ($hours <= 0) {
                throw ValidationException::withMessages([
                    'hours_used' => 'Hours used must be greater than 0.',
                ]);
            }

            $accuracy = isset($validated['gps_accuracy_meters']) ? (float) $validated['gps_accuracy_meters'] : null;
            $gpsVerified = $accuracy !== null && $accuracy > 0 && $accuracy <= 50;

            $log = EquipmentUsageLog::create([
                'project_id' => $project->id,
                'equipment_id' => $equipment->id,
                'member_id' => $memberId,
                'log_date' => $validated['log_date'],
                'hours_used' => $hours,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'gps_accuracy_meters' => $accuracy,
                'gps_verified' => $gpsVerified,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->activityService->log(
                module: 'equipment_usage',
                action: 'logged',
                actor: $actor,
                reference: $log,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'equipment_id' => $equipment->id,
                    'member_id' => $memberId,
                    'gps_verified' => $gpsVerified,
                    'hours_used' => $hours,
                ],
                request: $request
            );

            return $log->load(['equipment', 'member']);
        });
    }

    private function ensureMemberIsOnProject(Project $project, int $memberId, string $field): void
    {
        $isOnProject = ProjectTeamMember::query()
            ->where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->exists();

        if (!$isOnProject) {
            ProjectTeamMember::firstOrCreate(
                [
                    'project_id' => $project->id,
                    'member_id' => $memberId,
                ],
                [
                    'status' => 'active',
                    'role' => 'team_member',
                ]
            );
        }
    }
}
