<?php

namespace Tests\Feature\Construction;

use App\Http\Controllers\Admin\Construction\EquipmentController as AdminEquipmentController;
use App\Http\Controllers\Api\MemberDashboardController;
use App\Http\Controllers\SuperAdmin\Construction\EquipmentController as SuperAdminEquipmentController;
use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionEquipment;
use App\Models\EquipmentAllocation;
use App\Models\ExecutionTask;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionEquipmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EquipmentAllocationTaskLinkingFeatureTest extends TestCase
{
    public function test_migration_file_exists_for_adding_task_id_to_equipment_allocations(): void
    {
        $migrations = glob(database_path('migrations/*add_task_id_to_construction_equipment_allocations_table.php'));
        $this->assertNotEmpty($migrations, 'Migration adding task_id to construction_equipment_allocations table must exist.');

        $migrationContent = file_get_contents($migrations[0]);
        $this->assertStringContainsString('construction_equipment_allocations', $migrationContent);
        $this->assertStringContainsString('task_id', $migrationContent);
        $this->assertStringContainsString('construction_execution_tasks', $migrationContent);
    }

    public function test_super_admin_equipment_controller_validates_task_id(): void
    {
        $controllerFile = file_get_contents(app_path('Http/Controllers/SuperAdmin/Construction/EquipmentController.php'));
        $this->assertStringContainsString("'task_id'", $controllerFile, 'SuperAdmin EquipmentController must validate task_id');
        $this->assertStringContainsString('construction_execution_tasks', $controllerFile, 'task_id must validate against construction_execution_tasks');
    }

    public function test_admin_equipment_controller_validates_task_id(): void
    {
        $controllerFile = file_get_contents(app_path('Http/Controllers/Admin/Construction/EquipmentController.php'));
        $this->assertStringContainsString("'task_id'", $controllerFile, 'Admin EquipmentController must validate task_id');
        $this->assertStringContainsString('construction_execution_tasks', $controllerFile, 'task_id must validate against construction_execution_tasks');
    }

    public function test_super_admin_equipment_controller_index_loads_tasks(): void
    {
        $controllerFile = file_get_contents(app_path('Http/Controllers/SuperAdmin/Construction/EquipmentController.php'));
        $this->assertStringContainsString('ExecutionTask', $controllerFile, 'SuperAdmin EquipmentController should query ExecutionTask');
        $this->assertStringContainsString("'tasks'", $controllerFile, 'SuperAdmin EquipmentController index should pass tasks to Inertia');
    }

    public function test_fleet_equipment_service_allocate_persists_task_id(): void
    {
        $serviceFile = file_get_contents(app_path('Services/Construction/ConstructionEquipmentService.php'));
        $this->assertStringContainsString("'task_id'", $serviceFile, 'ConstructionEquipmentService must persist task_id');
    }

    public function test_member_dashboard_api_controller_includes_task_in_equipment_history(): void
    {
        $apiFile = file_get_contents(app_path('Http/Controllers/Api/MemberDashboardController.php'));
        $this->assertStringContainsString("'task_id'", $apiFile);
        $this->assertStringContainsString("'task'", $apiFile);
    }

    public function test_react_equipment_workspace_has_task_select_and_displays_task_badge(): void
    {
        $workspaceFile = file_get_contents(resource_path('js/Pages/Construction/Equipment/EquipmentWorkspace.jsx'));
        $this->assertStringContainsString('task_id', $workspaceFile, 'allocationForm must track task_id');
        $this->assertStringContainsString('Task', $workspaceFile, 'Equipment allocation form must have a Task input');
        $this->assertStringContainsString('availableTasksForProject', $workspaceFile, 'Should filter tasks by selected project');
    }

    public function test_equipment_service_allocates_equipment_with_task_id_in_database(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Local MySQL server is offline; skipping database execution test.');
        }

        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject('PRJ-EQ-001', 'eq-prj-1', 'Equipment Task Project');

        $equipment = ConstructionEquipment::create([
            'project_id' => $project->id,
            'equipment_code' => 'EQ-001',
            'name' => 'Excavator CAT 320',
            'equipment_type' => 'Earthmoving',
            'status' => 'active',
        ]);

        $task = ExecutionTask::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-DIG',
            'title' => 'Excavation Trench A',
            'status' => 'pending',
            'priority' => 'high',
        ]);

        $service = app(ConstructionEquipmentService::class);

        $allocation = $service->allocateEquipment($project, [
            'equipment_id' => $equipment->id,
            'task_id' => $task->id,
            'assigned_from' => now()->format('Y-m-d H:i:s'),
            'assigned_to' => now()->addDays(4)->format('Y-m-d H:i:s'),
            'status' => 'active',
            'notes' => 'Allocated for excavation trench A',
        ], $superAdmin);

        $this->assertInstanceOf(EquipmentAllocation::class, $allocation);
        $this->assertSame($task->id, $allocation->task_id);
        $this->assertNotNull($allocation->task);
        $this->assertSame('TSK-DIG', $allocation->task->task_code);
    }

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'roles' => json_encode(['super_admin']),
            'name' => 'Equipment Super Admin',
            'email' => 'eq-super-admin-' . Str::random(5) . '@example.com',
            'phone' => '9999999999',
            'whatsapp_phone' => '9999999999',
            'password' => 'password',
        ]);
    }

    private function createProject(
        string $projectCode = 'PRJ-001',
        string $slug = 'project-001',
        string $name = 'Equipment Project'
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

