<?php

namespace Tests\Feature\Driver;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\ConstructionVehicle;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Models\VehicleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverAppHomePageAndTripCompletionTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;
    private Member $driver;
    private Member $regularMember;
    private Company $company;
    private Client $client;
    private Project $project;
    private ExecutionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Apex Logistics Corp',
            'legal_name' => 'Apex Logistics Corporation Ltd',
            'email' => 'apex@example.com',
            'phone' => '9876543210',
            'status' => 'active',
        ]);

        $this->admin = SuperAdmin::create([
            'name' => 'Apex Admin',
            'email' => 'admin@apex.com',
            'password' => Hash::make('password'),
            'company_id' => $this->company->id,
            'role' => 'admin',
            'status' => 1,
        ]);

        $this->client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Apex Expressway Authority',
            'email' => 'authority@example.com',
            'phone' => '9876543211',
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Apex Expressway Project',
            'project_code' => 'PRJ-APEX-01',
            'status' => 'active',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->plan = ExecutionPlan::create([
            'project_id' => $this->project->id,
            'plan_code' => 'EP-APEX-01',
            'title' => 'Expressway Earthwork Plan',
            'status' => 'approved',
        ]);

        $driverRole = ConstructionRole::firstOrCreate(
            ['slug' => 'vehicle_driver'],
            ['name' => 'Driver', 'role_type' => 'construction', 'status' => 'active']
        );
        $driverRole->update(['status' => 'active']);

        $this->driver = Member::create([
            'name' => 'Vikram Driver',
            'slug' => 'vikram-driver',
            'username' => 'vikram_driver',
            'email' => 'vikram.driver@apex.com',
            'phone' => '9876500001',
            'password' => Hash::make('password'),
            'roles' => ['vehicle_driver'],
            'created_by' => $this->admin->id,
            'company_name' => $this->company->name,
            'status' => 1,
            'is_active' => true,
        ]);

        MemberRoleAssignment::create([
            'member_id' => $this->driver->id,
            'role_id' => $driverRole->id,
            'status' => 1,
        ]);

        $this->regularMember = Member::create([
            'name' => 'Rahul Site Engineer',
            'slug' => 'rahul-engineer',
            'username' => 'rahul_engineer',
            'email' => 'rahul.engineer@apex.com',
            'phone' => '9876500002',
            'password' => Hash::make('password'),
            'roles' => ['site_employee'],
            'created_by' => $this->admin->id,
            'company_name' => $this->company->name,
            'status' => 1,
            'is_active' => true,
        ]);
    }

    public function test_driver_home_page_excludes_separate_vehicle_and_equipment_sections(): void
    {
        $vehicle = ConstructionVehicle::create([
            'project_id' => $this->project->id,
            'vehicle_code' => 'VHC-APX-01',
            'registration_number' => 'RJ14-TR-9988',
            'status' => 'active',
        ]);

        VehicleAssignment::create([
            'project_id' => $this->project->id,
            'vehicle_id' => $vehicle->id,
            'driver_member_id' => $this->driver->id,
            'assigned_from' => now()->subDay(),
            'assigned_to' => now()->addDays(5),
            'status' => 'active',
        ]);

        $category = EquipmentCategory::create([
            'company_id' => $this->company->id,
            'category_name' => 'Survey GPS',
            'status' => 1,
        ]);

        Equipment::create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'equipment_name' => 'GPS Tracker Unit',
            'equipment_id' => 'EQ-TRK-01',
            'status' => 1,
        ]);

        // When Driver requests dashboard API
        $response = $this->actingAs($this->driver, 'sanctum')
            ->getJson('/api/member/dashboard');

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Separate sections must be empty for driver
        $data = $response->json();
        $this->assertEmpty($data['vehicles_assigned'], 'Driver home page must not display separate Assigned Vehicle section');
        $this->assertEmpty($data['equipment_assigned'], 'Driver home page must not display separate Assigned Equipment section');
        $this->assertEquals(0, $data['summary']['vehicles_assigned_count'] ?? 0);
        $this->assertEquals(0, $data['summary']['equipment_assigned_count'] ?? 0);
    }

    public function test_regular_member_dashboard_keeps_fleet_sections_intact(): void
    {
        $vehicle = ConstructionVehicle::create([
            'project_id' => $this->project->id,
            'vehicle_code' => 'VHC-APX-02',
            'registration_number' => 'RJ14-IV-5555',
            'status' => 'active',
        ]);

        VehicleAssignment::create([
            'project_id' => $this->project->id,
            'vehicle_id' => $vehicle->id,
            'driver_member_id' => $this->regularMember->id,
            'assigned_from' => now()->subDay(),
            'assigned_to' => now()->addDays(5),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->regularMember, 'sanctum')
            ->getJson('/api/member/dashboard');

        $response->assertOk();
        $data = $response->json();
        $this->assertNotEmpty($data['vehicles_assigned']);
        $this->assertEquals(1, $data['summary']['vehicles_assigned_count']);
    }

    public function test_submitting_completed_driver_trip_updates_execution_task_status_to_completed(): void
    {
        $task = ExecutionTask::create([
            'project_id' => $this->project->id,
            'execution_plan_id' => $this->plan->id,
            'task_code' => 'TSK-DRV-01',
            'title' => 'Deliver Sand to Batching Plant',
            'status' => 'in_progress',
            'progress_percent' => 30,
            'supervisor_member_id' => $this->driver->id,
        ]);

        $payload = [
            'task_id' => $task->id,
            'project_id' => $this->project->id,
            'vehicle_number' => 'RJ14-TR-9988',
            'start_location' => 'Sand Quarry',
            'destination' => 'Batching Plant Site B',
            'start_km' => 12000,
            'end_km' => 12045,
            'total_distance' => 45,
            'trip_status' => 'completed',
            'notes' => 'Trip finished smoothly and materials offloaded.',
        ];

        $response = $this->actingAs($this->driver, 'sanctum')
            ->postJson('/api/member/driver-duty/submit-data', $payload);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.is_completed', true);
        $response->assertJsonPath('data.task_status', 'completed');

        // Verify database state: task must be completed with 100% progress
        $task->refresh();
        $this->assertEquals('completed', $task->status, 'Execution task status must be updated to completed upon driver trip completion');
        $this->assertEquals(100, $task->progress_percent, 'Execution task progress must be updated to 100%');
        $this->assertNotNull($task->actual_end_date);
    }

    public function test_submitting_completed_driver_trip_syncs_unified_task_status_to_completed(): void
    {
        $unifiedTask = Task::create([
            'project_id' => $this->project->id,
            'task_code' => 'TSK-UNIF-02',
            'title' => 'Haul Concrete Mixers',
            'status' => 'in_progress',
            'progress_percent' => 50,
            'member_id' => $this->driver->id,
        ]);

        $legacyTask = ExecutionTask::create([
            'project_id' => $this->project->id,
            'execution_plan_id' => $this->plan->id,
            'task_code' => 'TSK-UNIF-02',
            'title' => 'Haul Concrete Mixers',
            'status' => 'in_progress',
            'progress_percent' => 50,
            'supervisor_member_id' => $this->driver->id,
        ]);

        $payload = [
            'task_id' => $legacyTask->id,
            'project_id' => $this->project->id,
            'vehicle_number' => 'RJ14-TR-9988',
            'start_location' => 'Quarry Plant',
            'destination' => 'Bridge Pier 4',
            'start_km' => 500,
            'end_km' => 530,
            'total_distance' => 30,
            'trip_status' => 'completed',
        ];

        $response = $this->actingAs($this->driver, 'sanctum')
            ->postJson("/api/member/tasks/{$legacyTask->id}/driver-trip", $payload);

        $response->assertOk();

        $legacyTask->refresh();
        $unifiedTask->refresh();

        $this->assertEquals('completed', $legacyTask->status);
        $this->assertEquals(100, $legacyTask->progress_percent);
        $this->assertEquals('completed', $unifiedTask->status);
        $this->assertEquals(100, $unifiedTask->progress_percent);
    }

    public function test_submitting_completed_driver_trip_auto_resolves_task_by_project_id(): void
    {
        $task = ExecutionTask::create([
            'project_id' => $this->project->id,
            'execution_plan_id' => $this->plan->id,
            'task_code' => 'TSK-AUTO-03',
            'title' => 'Daily Water Tanker Supply',
            'status' => 'in_progress',
            'progress_percent' => 20,
            'supervisor_member_id' => $this->driver->id,
        ]);

        // Submit trip with project_id, but omit task_id
        $payload = [
            'project_id' => $this->project->id,
            'vehicle_number' => 'RJ14-TR-9988',
            'start_location' => 'Water Reservoir',
            'destination' => 'Compaction Zone',
            'start_km' => 800,
            'end_km' => 815,
            'total_distance' => 15,
            'trip_status' => 'completed',
        ];

        $response = $this->actingAs($this->driver, 'sanctum')
            ->postJson('/api/member/driver-duty/submit-data', $payload);

        $response->assertOk();
        $response->assertJsonPath('data.task_id', $task->id);
        $response->assertJsonPath('data.task_status', 'completed');

        $task->refresh();
        $this->assertEquals('completed', $task->status);
        $this->assertEquals(100, $task->progress_percent);
    }

    public function test_driver_member_web_dashboard_omits_separate_assigned_vehicles_section(): void
    {
        $vehicle = ConstructionVehicle::create([
            'project_id' => $this->project->id,
            'vehicle_code' => 'VHC-APX-03',
            'registration_number' => 'RJ14-TR-1111',
            'status' => 'active',
        ]);

        VehicleAssignment::create([
            'project_id' => $this->project->id,
            'vehicle_id' => $vehicle->id,
            'driver_member_id' => $this->driver->id,
            'assigned_from' => now()->subDay(),
            'assigned_to' => now()->addDays(5),
            'status' => 'active',
        ]);

        $driverRole = ConstructionRole::where('slug', 'vehicle_driver')->first();
        $perm = \App\Models\Permission::firstOrCreate(
            ['slug' => 'dashboard.view'],
            ['name' => 'dashboard.view', 'module' => 'construction']
        );
        $driverRole->permissions()->syncWithoutDetaching([$perm->id]);

        \App\Models\MemberRoleAssignment::where('member_id', $this->driver->id)
            ->where('role_id', $driverRole->id)
            ->update(['project_id' => $this->project->id]);

        \App\Models\ProjectTeamMember::create([
            'project_id' => $this->project->id,
            'member_id' => $this->driver->id,
            'role_id' => $driverRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->driver, 'member')
            ->get(route('member.construction.dashboard') . '?project=' . $this->project->id . '&role=vehicle_driver');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Construction/Dashboard')
            ->missing('dashboard.assigned_vehicles')
        );
    }
}
