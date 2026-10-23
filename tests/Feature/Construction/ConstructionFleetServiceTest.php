<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\ConstructionVehicle;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionFleetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConstructionFleetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_location_pings_for_vehicles_from_another_project(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $projectA = $this->createProject('PRJ-A', 'project-a', 'Project A');
        $projectB = $this->createProject('PRJ-B', 'project-b', 'Project B');
        $member = $this->createMember($superAdmin, 'Tracking Member');

        ProjectTeamMember::create([
            'project_id' => $projectA->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $vehicleB = ConstructionVehicle::create([
            'project_id' => $projectB->id,
            'vehicle_code' => 'VEH-00001',
            'registration_number' => 'MH12AB1234',
            'status' => 'active',
        ]);

        $service = app(ConstructionFleetService::class);

        try {
            $service->recordLocationPing($projectA, [
                'vehicle_id' => $vehicleB->id,
                'latitude' => 19.0760,
                'longitude' => 72.8777,
                'gps_accuracy_meters' => 10,
            ], $member);

            $this->fail('Expected cross-project vehicle ping to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'The selected vehicle does not belong to the chosen project.',
            ], $exception->errors()['vehicle_id']);
        }
    }

    public function test_it_marks_gps_verified_based_on_accuracy_threshold(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Ping Member');

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $vehicle = ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => 'VEH-00002',
            'registration_number' => 'GJ01CD9999',
            'status' => 'active',
        ]);

        $service = app(ConstructionFleetService::class);

        $verifiedPing = $service->recordLocationPing($project, [
            'vehicle_id' => $vehicle->id,
            'latitude' => 23.0225,
            'longitude' => 72.5714,
            'gps_accuracy_meters' => 25,
        ], $member);

        $this->assertTrue($verifiedPing->gps_verified);

        $unverifiedPing = $service->recordLocationPing($project, [
            'vehicle_id' => $vehicle->id,
            'latitude' => 23.0225,
            'longitude' => 72.5714,
            'gps_accuracy_meters' => 120,
        ], $member);

        $this->assertFalse($unverifiedPing->gps_verified);
    }

    public function test_it_rejects_reported_by_member_not_assigned_to_project(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $assignedMember = $this->createMember($superAdmin, 'Assigned Member');
        $outsideMember = $this->createMember($superAdmin, 'Outside Member');

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $assignedMember->id,
            'status' => 'active',
        ]);

        $vehicle = ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => 'VEH-00003',
            'registration_number' => 'RJ14EF4444',
            'status' => 'active',
        ]);

        $service = app(ConstructionFleetService::class);

        try {
            $service->recordLocationPing($project, [
                'vehicle_id' => $vehicle->id,
                'reported_by_member_id' => $outsideMember->id,
                'latitude' => 26.9124,
                'longitude' => 75.7873,
                'gps_accuracy_meters' => 20,
            ], $assignedMember);

            $this->fail('Expected ping from non-project member to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'The selected member is not assigned to the chosen project.',
            ], $exception->errors()['reported_by_member_id']);
        }
    }

    public function test_it_assigns_main_vehicle_across_different_projects_without_unique_constraint_violation(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $projectA = $this->createProject('PRJ-A', 'project-a', 'Project A');
        $projectB = $this->createProject('PRJ-B', 'project-b', 'Project B');

        $mainVehicle = \App\Models\Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'vehicle_id' => 'VHC-000001',
            'vehicle_number' => 'RJ14DE3551',
            'vehicle_type' => 'Motorcycle',
            'vehicle_name' => 'Vida',
            'brand' => 'Hero',
            'fuel_type' => 'Electric',
            'status' => 1,
        ]);

        $service = app(ConstructionFleetService::class);

        // Assign to Project A
        $assignmentA = $service->assignVehicle($projectA, [
            'vehicle_id' => 'main_' . $mainVehicle->id,
            'status' => 'active',
        ], $superAdmin);

        $this->assertSame($projectA->id, $assignmentA->project_id);
        $this->assertSame('VHC-000001', $assignmentA->vehicle->vehicle_code);
        $this->assertSame($projectA->id, $assignmentA->vehicle->project_id);

        // Reassign to Project B (should NOT fail with unique constraint violation)
        $assignmentB = $service->assignVehicle($projectB, [
            'vehicle_id' => 'main_' . $mainVehicle->id,
            'status' => 'active',
        ], $superAdmin);

        $this->assertSame($projectB->id, $assignmentB->project_id);
        $this->assertSame('VHC-000001', $assignmentB->vehicle->vehicle_code);
        $this->assertSame($projectB->id, $assignmentB->vehicle->project_id);

        // Verify assignmentA was marked inactive
        $this->assertSame('inactive', $assignmentA->fresh()->status);
        $this->assertSame('active', $assignmentB->fresh()->status);
    }

    public function test_it_restores_soft_deleted_vehicle_when_reassigned(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();

        $mainVehicle = \App\Models\Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'vehicle_id' => 'VHC-000002',
            'vehicle_number' => 'RJ14DE9999',
            'vehicle_type' => 'Car',
            'vehicle_name' => 'Swift',
            'brand' => 'Maruti',
            'fuel_type' => 'Petrol',
            'status' => 1,
        ]);

        $cv = ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => 'VHC-000002',
            'registration_number' => 'RJ14DE9999',
            'status' => 'active',
        ]);
        $cv->delete();

        $this->assertTrue($cv->fresh()->trashed());

        $service = app(ConstructionFleetService::class);
        $assignment = $service->assignVehicle($project, [
            'vehicle_id' => 'main_' . $mainVehicle->id,
            'status' => 'active',
        ], $superAdmin);

        $this->assertFalse($assignment->vehicle->fresh()->trashed());
        $this->assertSame('VHC-000002', $assignment->vehicle->vehicle_code);
    }

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'roles' => json_encode(['super_admin']),
            'name' => 'Construction Super Admin',
            'email' => 'super-admin@example.com',
            'phone' => '9999999999',
            'whatsapp_phone' => '9999999999',
            'password' => 'password',
        ]);
    }

    private function createMember(SuperAdmin $superAdmin, string $name): Member
    {
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(5));

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'name' => $name,
            'username' => $slug,
            'email' => $slug . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'slug' => $slug,
        ]);
    }

    private function createProject(
        string $projectCode = 'PRJ-001',
        string $slug = 'project-001',
        string $name = 'Construction Project'
    ): Project {
        $company = Company::create([
            'name' => $name . ' Company',
            'status' => 'active',
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => $projectCode . '-CLIENT',
            'name' => $name . ' Client',
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => $projectCode,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'current_stage' => 'ready_for_construction',
        ]);
    }
}

