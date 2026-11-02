<?php

namespace App\Services\Construction;

use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\ConstructionVehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLocationPing;
use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConstructionFleetService
{
    public function __construct(
        private readonly ConstructionActivityService $activityService
    ) {
    }

    public function createVehicle(Project $project, array $validated, ?Model $actor, ?Request $request = null): ConstructionVehicle
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $registrationNumber = strtoupper(trim((string) $validated['registration_number']));

            $exists = ConstructionVehicle::withTrashed()
                ->where('project_id', $project->id)
                ->where('registration_number', $registrationNumber)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'registration_number' => 'This registration number already exists for the selected project.',
                ]);
            }

            if (!empty($validated['vehicle_code'])) {
                $code = trim((string) $validated['vehicle_code']);
                if (ConstructionVehicle::withTrashed()->where('vehicle_code', $code)->exists()) {
                    throw ValidationException::withMessages([
                        'vehicle_code' => 'This vehicle code already exists.',
                    ]);
                }
                $vehicleCode = $code;
            } else {
                $vehicleCode = ConstructionVehicle::generateVehicleCode();
            }

            $vehicle = ConstructionVehicle::create([
                'project_id' => $project->id,
                'vehicle_code' => $vehicleCode,
                'registration_number' => $registrationNumber,
                'vehicle_type' => $validated['vehicle_type'] ?? null,
                'make' => $validated['make'] ?? null,
                'model' => $validated['model'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'created_by_type' => $actor ? $actor::class : null,
                'created_by_id' => $actor?->getKey(),
            ]);

            $this->activityService->log(
                module: 'vehicle',
                action: 'created',
                actor: $actor,
                reference: $vehicle,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'vehicle_code' => $vehicle->vehicle_code,
                    'registration_number' => $vehicle->registration_number,
                ],
                request: $request
            );

            return $vehicle;
        });
    }

    public function resolveOrCreateFromMainVehicle(
        Project $project,
        \App\Models\Vehicle $mainVehicle,
        ?Model $actor
    ): ConstructionVehicle {
        $desiredCode = $mainVehicle->vehicle_id ?: ('VHC-' . str_pad((string) $mainVehicle->id, 6, '0', STR_PAD_LEFT));
        $regNumber = strtoupper(trim((string) ($mainVehicle->vehicle_number ?: $desiredCode)));

        // 1. Find if this vehicle already exists in construction_vehicles by code or registration
        /** @var ConstructionVehicle|null $vehicle */
        $vehicle = ConstructionVehicle::withTrashed()
            ->where(function ($query) use ($desiredCode, $regNumber) {
                $query->where('vehicle_code', $desiredCode);
                if (!empty($regNumber)) {
                    $query->orWhere('registration_number', $regNumber);
                }
            })
            ->first();

        if ($vehicle) {
            if ($vehicle->trashed()) {
                $vehicle->restore();
            }

            $vehicle->project_id = $project->id;
            if (empty($vehicle->vehicle_code)) {
                $vehicle->vehicle_code = $desiredCode;
            }
            if (!empty($regNumber)) {
                $vehicle->registration_number = $regNumber;
            }
            if (!empty($mainVehicle->vehicle_type)) {
                $vehicle->vehicle_type = $mainVehicle->vehicle_type;
            }
            $vehicle->make = $mainVehicle->brand ?? $mainVehicle->vehicle_name ?? $vehicle->make;
            $vehicle->model = $mainVehicle->vehicle_name ?? $vehicle->model;
            $vehicle->status = 'active';
            $vehicle->save();

            return $vehicle;
        }

        // 2. If no record was found, ensure code uniqueness before creating
        $codeToUse = $desiredCode;
        if (ConstructionVehicle::withTrashed()->where('vehicle_code', $codeToUse)->exists()) {
            $codeToUse = ConstructionVehicle::generateVehicleCode('VHC-');
        }

        // Check if registration number already exists in this project
        $existingInProject = ConstructionVehicle::withTrashed()
            ->where('project_id', $project->id)
            ->where('registration_number', $regNumber)
            ->first();

        if ($existingInProject) {
            if ($existingInProject->trashed()) {
                $existingInProject->restore();
            }
            $existingInProject->vehicle_type = $mainVehicle->vehicle_type ?: $existingInProject->vehicle_type;
            $existingInProject->make = $mainVehicle->brand ?? $mainVehicle->vehicle_name ?? $existingInProject->make;
            $existingInProject->model = $mainVehicle->vehicle_name ?? $existingInProject->model;
            $existingInProject->status = 'active';
            $existingInProject->save();

            return $existingInProject;
        }

        return ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => $codeToUse,
            'registration_number' => $regNumber,
            'vehicle_type' => $mainVehicle->vehicle_type,
            'make' => $mainVehicle->brand ?? $mainVehicle->vehicle_name,
            'model' => $mainVehicle->vehicle_name,
            'status' => 'active',
            'created_by_type' => $actor ? $actor::class : null,
            'created_by_id' => $actor?->getKey(),
        ]);
    }

    public function expirePastAssignments(): int
    {
        return VehicleAssignment::query()
            ->where('status', 'active')
            ->whereNotNull('assigned_to')
            ->where('assigned_to', '<', now())
            ->update([
                'status' => 'inactive',
            ]);
    }

    public function assignVehicle(Project $project, array $validated, ?Model $actor, ?Request $request = null): VehicleAssignment
    {
        $this->expirePastAssignments();

        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $rawVehicleId = (string) $validated['vehicle_id'];
            /** @var ConstructionVehicle|null $vehicle */
            $vehicle = null;

            if (str_starts_with($rawVehicleId, 'main_')) {
                $mainId = (int) substr($rawVehicleId, 5);
                $mainVehicle = \App\Models\Vehicle::find($mainId);
                if ($mainVehicle) {
                    $vehicle = $this->resolveOrCreateFromMainVehicle($project, $mainVehicle, $actor);
                }
            } elseif (str_starts_with($rawVehicleId, 'construction_') || str_starts_with($rawVehicleId, 'cv_')) {
                $cvId = (int) preg_replace('/^\D+/', '', $rawVehicleId);
                $vehicle = ConstructionVehicle::withTrashed()->find($cvId);
                if ($vehicle) {
                    if ($vehicle->trashed()) {
                        $vehicle->restore();
                    }
                    if ($vehicle->project_id !== $project->id) {
                        $vehicle->project_id = $project->id;
                        $vehicle->save();
                    }
                }
            } else {
                $vehicleId = (int) $rawVehicleId;
                $vehicle = ConstructionVehicle::withTrashed()
                    ->whereKey($vehicleId)
                    ->where('project_id', $project->id)
                    ->first();

                if (!$vehicle) {
                    $vehicle = ConstructionVehicle::withTrashed()->whereKey($vehicleId)->first();
                    if ($vehicle) {
                        if ($vehicle->trashed()) {
                            $vehicle->restore();
                        }
                        if ($vehicle->project_id !== $project->id) {
                            $vehicle->project_id = $project->id;
                            $vehicle->save();
                        }
                    }
                }

                if (!$vehicle) {
                    $mainVehicle = \App\Models\Vehicle::find($vehicleId);
                    if ($mainVehicle) {
                        $vehicle = $this->resolveOrCreateFromMainVehicle($project, $mainVehicle, $actor);
                    }
                }
            }

            if (!$vehicle) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'The selected vehicle was not found in the vehicle registry.',
                ]);
            }

            $driverMemberId = isset($validated['driver_member_id']) ? (int) $validated['driver_member_id'] : null;

            if ($driverMemberId) {
                $isOnProject = ProjectTeamMember::query()
                    ->where('project_id', $project->id)
                    ->where('member_id', $driverMemberId)
                    ->where('status', 'active')
                    ->exists();

                if (!$isOnProject) {
                    ProjectTeamMember::firstOrCreate(
                        [
                            'project_id' => $project->id,
                            'member_id' => $driverMemberId,
                        ],
                        [
                            'status' => 'active',
                            'role' => 'driver',
                        ]
                    );
                }
            }

            $assignedFrom = isset($validated['assigned_from']) && !empty($validated['assigned_from'])
                ? \Illuminate\Support\Carbon::parse($validated['assigned_from'])
                : now();
            $assignedTo = isset($validated['assigned_to']) && !empty($validated['assigned_to'])
                ? \Illuminate\Support\Carbon::parse($validated['assigned_to'])
                : null;

            // Check if vehicle has an active assignment overlapping with [assigned_from, assigned_to]
            $vehicleOverlap = VehicleAssignment::query()
                ->where('vehicle_id', $vehicle->id)
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

            if ($vehicleOverlap) {
                $startFormatted = $vehicleOverlap->assigned_from ? $vehicleOverlap->assigned_from->format('d/m/Y H:i') : 'Start';
                $endFormatted = $vehicleOverlap->assigned_to ? $vehicleOverlap->assigned_to->format('d/m/Y H:i') : 'Ongoing';
                throw ValidationException::withMessages([
                    'vehicle_id' => "This vehicle is already assigned from {$startFormatted} to {$endFormatted}. Please select a different date range or vehicle.",
                ]);
            }

            if ($driverMemberId) {
                $driverOverlap = VehicleAssignment::query()
                    ->where('driver_member_id', $driverMemberId)
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

                if ($driverOverlap) {
                    $dStart = $driverOverlap->assigned_from ? $driverOverlap->assigned_from->format('d/m/Y H:i') : 'Start';
                    $dEnd = $driverOverlap->assigned_to ? $driverOverlap->assigned_to->format('d/m/Y H:i') : 'Ongoing';
                    throw ValidationException::withMessages([
                        'driver_member_id' => "This driver is already assigned to another vehicle from {$dStart} to {$dEnd}.",
                    ]);
                }
            }

            $notes = $validated['note'] ?? $validated['notes'] ?? null;

            $assignment = VehicleAssignment::create([
                'project_id' => $project->id,
                'vehicle_id' => $vehicle->id,
                'driver_member_id' => $driverMemberId,
                'assigned_from' => $assignedFrom,
                'assigned_to' => $assignedTo,
                'notes' => $notes,
                'status' => $validated['status'] ?? 'active',
                'assigned_by_type' => $actor ? $actor::class : null,
                'assigned_by_id' => $actor?->getKey(),
            ]);

            $this->activityService->log(
                module: 'vehicle_assignment',
                action: 'created',
                actor: $actor,
                reference: $assignment,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'vehicle_id' => $vehicle->id,
                    'driver_member_id' => $driverMemberId,
                    'status' => $assignment->status,
                ],
                request: $request
            );

            return $assignment->load(['vehicle', 'driver']);
        });
    }

    public function recordLocationPing(Project $project, array $validated, ?Model $actor, ?Request $request = null): VehicleLocationPing
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $vehicle = ConstructionVehicle::query()
                ->whereKey((int) $validated['vehicle_id'])
                ->where('project_id', $project->id)
                ->first();

            if (!$vehicle) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'The selected vehicle does not belong to the chosen project.',
                ]);
            }

            $reportedByMemberId = isset($validated['reported_by_member_id'])
                ? (int) $validated['reported_by_member_id']
                : ($actor instanceof Member ? (int) $actor->getKey() : null);

            if ($reportedByMemberId) {
                $isOnProject = ProjectTeamMember::query()
                    ->where('project_id', $project->id)
                    ->where('member_id', $reportedByMemberId)
                    ->where('status', 'active')
                    ->exists();

                if (!$isOnProject) {
                    throw ValidationException::withMessages([
                        'reported_by_member_id' => 'The selected member is not assigned to the chosen project.',
                    ]);
                }
            }

            $accuracy = isset($validated['gps_accuracy_meters']) ? (float) $validated['gps_accuracy_meters'] : null;
            $gpsVerified = $accuracy !== null && $accuracy > 0 && $accuracy <= 50;

            $ping = VehicleLocationPing::create([
                'project_id' => $project->id,
                'vehicle_id' => $vehicle->id,
                'reported_by_member_id' => $reportedByMemberId,
                'recorded_at' => $validated['recorded_at'] ?? now(),
                'latitude' => (float) $validated['latitude'],
                'longitude' => (float) $validated['longitude'],
                'gps_accuracy_meters' => $accuracy,
                'speed_kmph' => isset($validated['speed_kmph']) ? (float) $validated['speed_kmph'] : null,
                'heading_degrees' => isset($validated['heading_degrees']) ? (float) $validated['heading_degrees'] : null,
                'odometer_km' => isset($validated['odometer_km']) ? (float) $validated['odometer_km'] : null,
                'gps_verified' => $gpsVerified,
                'source' => $validated['source'] ?? ($request?->expectsJson() ? 'mobile' : 'web'),
            ]);

            $this->activityService->log(
                module: 'vehicle_tracking',
                action: 'ping_recorded',
                actor: $actor,
                reference: $ping,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'vehicle_id' => $vehicle->id,
                    'gps_verified' => $gpsVerified,
                    'gps_accuracy_meters' => $accuracy,
                ],
                request: $request
            );

            return $ping->load(['vehicle', 'reportedBy']);
        });
    }

    public function returnVehicleAssignment(VehicleAssignment $assignment, ?Model $actor, ?Request $request = null): VehicleAssignment
    {
        return DB::transaction(function () use ($assignment, $actor, $request) {
            $assignment->update([
                'status' => 'inactive',
                'assigned_to' => now(),
            ]);

            $this->activityService->log(
                module: 'vehicle_assignment',
                action: 'returned',
                actor: $actor,
                reference: $assignment,
                companyId: $assignment->project?->company_id,
                projectId: $assignment->project_id,
                meta: [
                    'vehicle_id' => $assignment->vehicle_id,
                    'driver_member_id' => $assignment->driver_member_id,
                ],
                request: $request
            );

            return $assignment;
        });
    }
}
