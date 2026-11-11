<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionEquipment;
use App\Models\ConstructionVehicle;
use App\Models\EquipmentAllocation;
use App\Models\ExecutionTask;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Models\VehicleAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberDashboardAssignedTaskCompletedFilterTest extends TestCase
{
    public function test_controller_code_explicitly_filters_out_completed_tasks_from_assigned_vehicles_and_equipment(): void
    {
        $controllerContent = file_get_contents(app_path('Http/Controllers/Api/MemberDashboardController.php'));

        preg_match('/public function index\(Request \$request\)(.*?)(public function|\Z)/s', $controllerContent, $matches);
        $indexMethod = $matches[1] ?? '';
        $this->assertNotEmpty($indexMethod, 'MemberDashboardController::index method must exist');

        // Between $vehiclesAssigned = collect([]); and $equipmentAssigned = collect([]);
        preg_match('/\$vehiclesAssigned\s*=\s*collect\(\[\]\);(.*?)\$equipmentAssigned/s', $indexMethod, $vMatches);
        $vehiclesBlock = $vMatches[1] ?? '';
        $this->assertNotEmpty($vehiclesBlock, 'Vehicles assigned block must exist in index()');
        $this->assertStringContainsString('task', $vehiclesBlock, 'Vehicles block in index() must reference task');
        $this->assertStringContainsString('completed', $vehiclesBlock, 'Vehicles block in index() must filter out completed tasks');

        // Between $equipmentAssigned = collect([]); and $projectStageCounts
        preg_match('/\$equipmentAssigned\s*=\s*collect\(\[\]\);(.*?)\$projectStageCounts/s', $indexMethod, $eMatches);
        $equipmentBlock = $eMatches[1] ?? '';
        $this->assertNotEmpty($equipmentBlock, 'Equipment assigned block must exist in index()');
        $this->assertStringContainsString('task', $equipmentBlock, 'Equipment block in index() must reference task');
        $this->assertStringContainsString('completed', $equipmentBlock, 'Equipment block in index() must filter out completed tasks');
    }

    public function test_dashboard_removes_assigned_vehicle_when_linked_task_is_completed(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Local MySQL server is offline; skipping database execution test.');
        }

        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Driver Test');

        $completedTask = ExecutionTask::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-COMPLETED',
            'title' => 'Finished Grading',
            'status' => 'completed',
            'progress_percent' => 100,
        ]);

        $inProgressTask = ExecutionTask::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-ACTIVE',
            'title' => 'Active Excavation',
            'status' => 'in_progress',
            'progress_percent' => 30,
        ]);

        $vehicle1 = ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => 'VHC-001',
            'registration_number' => 'DL01AA0001',
            'status' => 'active',
        ]);

        $vehicle2 = ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => 'VHC-002',
            'registration_number' => 'DL01AA0002',
            'status' => 'active',
        ]);

        // Assignment 1: linked to completed task -> MUST BE REMOVED FROM DASHBOARD
        VehicleAssignment::create([
            'project_id' => $project->id,
            'vehicle_id' => $vehicle1->id,
            'driver_member_id' => $member->id,
            'task_id' => $completedTask->id,
            'assigned_from' => now()->subDays(2),
            'assigned_to' => now()->addDays(2),
            'status' => 'active',
        ]);

        // Assignment 2: linked to in-progress task -> MUST REMAIN ON DASHBOARD
        VehicleAssignment::create([
            'project_id' => $project->id,
            'vehicle_id' => $vehicle2->id,
            'driver_member_id' => $member->id,
            'task_id' => $inProgressTask->id,
            'assigned_from' => now()->subDay(),
            'assigned_to' => now()->addDays(3),
            'status' => 'active',
        ]);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/member/dashboard');
        $response->assertOk();
        $response->assertJsonPath('success', true);

        $assignedVehicles = collect($response->json('vehicles_assigned') ?? []);
        $assignedVehicleCodes = $assignedVehicles->pluck('vehicle.vehicle_code')->filter()->all();

        $this->assertNotContains('VHC-001', $assignedVehicleCodes, 'Vehicle with completed task must be removed from vehicles_assigned');
        $this->assertContains('VHC-002', $assignedVehicleCodes, 'Vehicle with active in-progress task must remain in vehicles_assigned');
        $this->assertSame(1, $response->json('summary.vehicles_assigned_count'));
    }

    public function test_dashboard_removes_assigned_equipment_when_linked_task_is_completed(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Local MySQL server is offline; skipping database execution test.');
        }

        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject();
        $member = $this->createMember($superAdmin, 'Equipment Operator');

        $completedTask = ExecutionTask::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-EQ-DONE',
            'title' => 'Finished Foundation',
            'status' => 'completed',
            'progress_percent' => 100,
        ]);

        $activeTask = ExecutionTask::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-EQ-RUN',
            'title' => 'Ongoing Piling',
            'status' => 'in_progress',
            'progress_percent' => 50,
        ]);

        $equipment1 = ConstructionEquipment::create([
            'project_id' => $project->id,
            'equipment_code' => 'EQ-DONE-1',
            'name' => 'Concrete Mixer',
            'status' => 'active',
        ]);

        $equipment2 = ConstructionEquipment::create([
            'project_id' => $project->id,
            'equipment_code' => 'EQ-RUN-2',
            'name' => 'Crane 50T',
            'status' => 'active',
        ]);

        // Allocation 1: linked to completed task -> MUST BE REMOVED FROM DASHBOARD
        EquipmentAllocation::create([
            'project_id' => $project->id,
            'equipment_id' => $equipment1->id,
            'assigned_to_member_id' => $member->id,
            'task_id' => $completedTask->id,
            'assigned_from' => now()->subDays(2),
            'assigned_to' => now()->addDays(2),
            'status' => 'active',
        ]);

        // Allocation 2: linked to active task -> MUST REMAIN ON DASHBOARD
        EquipmentAllocation::create([
            'project_id' => $project->id,
            'equipment_id' => $equipment2->id,
            'assigned_to_member_id' => $member->id,
            'task_id' => $activeTask->id,
            'assigned_from' => now()->subDay(),
            'assigned_to' => now()->addDays(4),
            'status' => 'active',
        ]);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/member/dashboard');
        $response->assertOk();
        $response->assertJsonPath('success', true);

        $assignedEquipments = collect($response->json('equipment_assigned') ?? []);
        $assignedEqCodes = $assignedEquipments->map(function ($item) {
            return $item['equipment']['equipment_code'] ?? ($item['equipment_code'] ?? null);
        })->filter()->all();

        $this->assertNotContains('EQ-DONE-1', $assignedEqCodes, 'Equipment with completed task must be removed from equipment_assigned');
        $this->assertContains('EQ-RUN-2', $assignedEqCodes, 'Equipment with active task must remain in equipment_assigned');
        $this->assertSame(1, $response->json('summary.equipment_assigned_count'));
    }

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'roles' => json_encode(['super_admin']),
            'name' => 'Construction Super Admin',
            'email' => 'super-admin-' . Str::random(5) . '@example.com',
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
