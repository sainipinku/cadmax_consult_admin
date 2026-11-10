<?php

namespace Tests\Feature\Construction;

use App\Http\Controllers\Admin\Construction\VehiclesController as AdminVehiclesController;
use App\Http\Controllers\Api\MemberDashboardController;
use App\Http\Controllers\SuperAdmin\Construction\VehiclesController as SuperAdminVehiclesController;
use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionVehicle;
use App\Models\ExecutionTask;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\VehicleAssignment;
use App\Services\Construction\ConstructionFleetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VehicleAssignmentTaskLinkingFeatureTest extends TestCase
{
    public function test_migration_file_exists_for_adding_task_id_to_vehicle_assignments(): void
    {
        $migrations = glob(database_path('migrations/*add_task_id_to_construction_vehicle_assignments_table.php'));
        $this->assertNotEmpty($migrations, 'Migration adding task_id to construction_vehicle_assignments table must exist.');

        $migrationContent = file_get_contents($migrations[0]);
        $this->assertStringContainsString('construction_vehicle_assignments', $migrationContent);
        $this->assertStringContainsString('task_id', $migrationContent);
        $this->assertStringContainsString('construction_execution_tasks', $migrationContent);
    }

    public function test_super_admin_vehicles_controller_validates_task_id(): void
    {
        $controllerFile = file_get_contents(app_path('Http/Controllers/SuperAdmin/Construction/VehiclesController.php'));
        $this->assertStringContainsString("'task_id'", $controllerFile, 'SuperAdmin VehiclesController must validate task_id');
        $this->assertStringContainsString('construction_execution_tasks', $controllerFile, 'task_id must validate against construction_execution_tasks');
    }

    public function test_admin_vehicles_controller_validates_task_id(): void
    {
        $controllerFile = file_get_contents(app_path('Http/Controllers/Admin/Construction/VehiclesController.php'));
        $this->assertStringContainsString("'task_id'", $controllerFile, 'Admin VehiclesController must validate task_id');
        $this->assertStringContainsString('construction_execution_tasks', $controllerFile, 'task_id must validate against construction_execution_tasks');
    }

    public function test_super_admin_controller_index_loads_tasks(): void
    {
        $controllerFile = file_get_contents(app_path('Http/Controllers/SuperAdmin/Construction/VehiclesController.php'));
        $this->assertStringContainsString('ExecutionTask', $controllerFile, 'SuperAdmin VehiclesController should query ExecutionTask');
        $this->assertStringContainsString("'tasks'", $controllerFile, 'SuperAdmin VehiclesController index should pass tasks to Inertia');
    }

    public function test_fleet_service_assign_vehicle_persists_task_id(): void
    {
        $serviceFile = file_get_contents(app_path('Services/Construction/ConstructionFleetService.php'));
        $this->assertStringContainsString("'task_id'", $serviceFile, 'ConstructionFleetService must persist task_id');
    }

    public function test_member_dashboard_api_controller_includes_task_in_vehicle_history(): void
    {
        $apiFile = file_get_contents(app_path('Http/Controllers/Api/MemberDashboardController.php'));
        $this->assertStringContainsString("'task'", $apiFile);
        $this->assertStringContainsString("'task_id'", $apiFile);
    }

    public function test_react_vehicles_workspace_has_task_select_and_displays_task_badge(): void
    {
        $workspaceFile = file_get_contents(resource_path('js/Pages/Construction/Vehicles/VehiclesWorkspace.jsx'));
        $this->assertStringContainsString('task_id', $workspaceFile, 'assignmentForm must track task_id');
        $this->assertStringContainsString('Task', $workspaceFile, 'Driver assignment form must have a Task input');
        $this->assertStringContainsString('availableTasksForProject', $workspaceFile, 'Should filter tasks by selected project');
    }

    public function test_fleet_service_assigns_vehicle_with_task_id_in_database(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Local MySQL server is offline; skipping database execution test.');
        }

        $superAdmin = $this->createSuperAdmin();
        $project = $this->createProject('PRJ-TASK-001', 'task-prj-1', 'Fleet Task Project');

        $vehicle = ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => 'VHC-00101',
            'registration_number' => 'DL01AB1111',
            'status' => 'active',
        ]);

        $task = ExecutionTask::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-001',
            'title' => 'Excavation Phase 1',
            'status' => 'pending',
            'priority' => 'high',
        ]);

        $service = app(ConstructionFleetService::class);

        $assignment = $service->assignVehicle($project, [
            'vehicle_id' => $vehicle->id,
            'task_id' => $task->id,
            'assigned_from' => now()->format('Y-m-d H:i:s'),
            'assigned_to' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'status' => 'active',
            'note' => 'Allocated for site excavation task',
        ], $superAdmin);

        $this->assertInstanceOf(VehicleAssignment::class, $assignment);
        $this->assertSame($task->id, $assignment->task_id);
        $this->assertNotNull($assignment->task);
        $this->assertSame('TSK-001', $assignment->task->task_code);
        $this->assertSame('Excavation Phase 1', $assignment->task->title);
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
