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

            $exists = ConstructionVehicle::query()
                ->where('project_id', $project->id)
                ->where('registration_number', $registrationNumber)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'registration_number' => 'This registration number already exists for the selected project.',
                ]);
            }

            $nextId = (ConstructionVehicle::max('id') ?? 0) + 1;
            $vehicleCode = $validated['vehicle_code'] ?? ('VEH-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT));

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

    public function assignVehicle(Project $project, array $validated, ?Model $actor, ?Request $request = null): VehicleAssignment
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $rawVehicleId = (string) $validated['vehicle_id'];
            /** @var ConstructionVehicle|null $vehicle */
            $vehicle = null;

            if (str_starts_with($rawVehicleId, 'main_')) {
                $mainId = (int) substr($rawVehicleId, 5);
                $mainVehicle = \App\Models\Vehicle::find($mainId);
                if ($mainVehicle) {
                    $vehicle = ConstructionVehicle::firstOrCreate(
                        [
                            'project_id' => $project->id,
                            'registration_number' => $mainVehicle->vehicle_number ?: ($mainVehicle->vehicle_id ?? ('VHC-' . $mainVehicle->id)),
                        ],
                        [
                            'vehicle_code' => $mainVehicle->vehicle_id ?? ('VHC-' . $mainVehicle->id),
                            'vehicle_type' => $mainVehicle->vehicle_type,
                            'make' => $mainVehicle->brand ?? $mainVehicle->vehicle_name,
                            'model' => $mainVehicle->vehicle_name,
                            'status' => 'active',
                            'created_by_type' => $actor ? $actor::class : null,
                            'created_by_id' => $actor?->getKey(),
                        ]
                    );
                }
            } elseif (str_starts_with($rawVehicleId, 'construction_') || str_starts_with($rawVehicleId, 'cv_')) {
                $cvId = (int) preg_replace('/^\D+/', '', $rawVehicleId);
                $vehicle = ConstructionVehicle::find($cvId);
            } else {
                $vehicleId = (int) $rawVehicleId;
                $vehicle = ConstructionVehicle::query()
                    ->whereKey($vehicleId)
                    ->where('project_id', $project->id)
                    ->first();

                if (!$vehicle) {
                    $vehicle = ConstructionVehicle::query()->whereKey($vehicleId)->first();
                }

                if (!$vehicle) {
                    $mainVehicle = \App\Models\Vehicle::find($vehicleId);
                    if ($mainVehicle) {
                        $vehicle = ConstructionVehicle::firstOrCreate(
                            [
                                'project_id' => $project->id,
                                'registration_number' => $mainVehicle->vehicle_number ?: ($mainVehicle->vehicle_id ?? ('VHC-' . $mainVehicle->id)),
                            ],
                            [
                                'vehicle_code' => $mainVehicle->vehicle_id ?? ('VHC-' . $mainVehicle->id),
                                'vehicle_type' => $mainVehicle->vehicle_type,
                                'make' => $mainVehicle->brand ?? $mainVehicle->vehicle_name,
                                'model' => $mainVehicle->vehicle_name,
                                'status' => 'active',
                                'created_by_type' => $actor ? $actor::class : null,
                                'created_by_id' => $actor?->getKey(),
                            ]
                        );
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

            $assignedFrom = isset($validated['assigned_from']) ? $validated['assigned_from'] : now();

            VehicleAssignment::query()
                ->where('project_id', $project->id)
                ->where('vehicle_id', $vehicle->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'inactive',
                    'assigned_to' => now(),
                ]);

            $notes = $validated['note'] ?? $validated['notes'] ?? null;

            $assignment = VehicleAssignment::create([
                'project_id' => $project->id,
                'vehicle_id' => $vehicle->id,
                'driver_member_id' => $driverMemberId,
                'assigned_from' => $assignedFrom,
                'assigned_to' => $validated['assigned_to'] ?? null,
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
}
