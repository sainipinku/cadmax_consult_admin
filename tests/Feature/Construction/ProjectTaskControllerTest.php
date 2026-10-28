<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Models\Task;
use Database\Seeders\Construction\FoundationSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(): Company
    {
        return Company::create([
            'name' => 'Cadmax Infra',
            'status' => 'active',
        ]);
    }

    private function createSuperAdmin(Company $company): SuperAdmin
    {
        static $seq = 200;
        $seq++;

        return SuperAdmin::create([
            'name' => 'Task Admin',
            'email' => "taskadmin{$seq}@cadmax.com",
            'username' => "taskadmin{$seq}",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 1,
            'is_project_owner' => 0,
        ]);
    }

    private function createProject(Company $company, SuperAdmin $admin): Project
    {
        $client = \App\Models\Client::create([
            'company_id' => $company->id,
            'name' => 'Client Road',
            'client_code' => 'CLI-RD-' . rand(100, 999),
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Highway Project',
            'slug' => 'highway-project-' . rand(100, 999),
            'project_code' => 'PRJ-HWY',
            'status' => 'active',
        ]);
    }

    public function test_super_admin_can_update_task_via_tasks_v2_endpoint(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);

        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $project = $this->createProject($company, $admin);

        $task = Task::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-001',
            'title' => 'Initial Title',
            'task_source' => 'admin_created',
            'task_type' => 'one_time',
            'status' => 'planned',
            'priority' => 'medium',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin, 'superadmin')
            ->put(route('super.construction.projects.tasks-v2.update', [
                'project' => $project->id,
                'task' => $task->id,
            ]), [
                'title' => 'Updated Task Title',
                'status' => 'in_progress',
                'priority' => 'high',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $task->refresh();
        $this->assertEquals('Updated Task Title', $task->title);
        $this->assertEquals('in_progress', $task->status);
        $this->assertEquals('high', $task->priority->value ?? (string) $task->priority);
    }

    public function test_super_admin_can_delete_task_via_tasks_v2_endpoint(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);

        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $project = $this->createProject($company, $admin);

        $task = Task::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-002',
            'title' => 'Task To Delete',
            'task_source' => 'admin_created',
            'task_type' => 'one_time',
            'status' => 'planned',
            'priority' => 'low',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin, 'superadmin')
            ->delete(route('super.construction.projects.tasks-v2.destroy', [
                'project' => $project->id,
                'task' => $task->id,
            ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertSoftDeleted('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_super_admin_can_store_task_via_tasks_v2_endpoint(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);

        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $project = $this->createProject($company, $admin);

        $response = $this->actingAs($admin, 'superadmin')
            ->post(route('super.construction.projects.tasks-v2.store', [
                'project' => $project->id,
            ]), [
                'title' => 'Brand New Task',
                'task_source' => 'admin_created',
                'task_type' => 'one_time',
                'status' => 'planned',
                'priority' => 'high',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Brand New Task',
            'priority' => 'high',
        ]);
    }

    public function test_super_admin_can_replace_assignments_via_tasks_v2_endpoint(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);

        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $project = $this->createProject($company, $admin);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $admin->id,
            'company_id' => $company->id,
            'name' => 'Executor Member',
            'username' => 'exec_member_' . rand(100, 999),
            'slug' => 'exec-member-' . rand(1000, 9999),
            'phone' => '9' . rand(100000000, 999999999),
            'email' => 'exec_' . rand(100, 999) . '@cadmax.com',
            'password' => Hash::make('password'),
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-003',
            'title' => 'Task for Assignments',
            'task_source' => 'admin_created',
            'task_type' => 'one_time',
            'status' => 'planned',
            'priority' => 'medium',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin, 'superadmin')
            ->post(route('super.construction.projects.tasks-v2.assignments.replace', [
                'project' => $project->id,
                'task' => $task->id,
            ]), [
                'assignments' => [
                    [
                        'member_id' => $member->id,
                        'assignment_role' => 'executor',
                        'is_primary' => true,
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'assigned_to' => $member->id,
            'is_primary' => 1,
        ]);
    }

    public function test_super_admin_can_save_checklist_via_tasks_v2_endpoint(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);

        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $project = $this->createProject($company, $admin);

        $task = Task::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-004',
            'title' => 'Task for Checklists',
            'task_source' => 'admin_created',
            'task_type' => 'one_time',
            'status' => 'planned',
            'priority' => 'medium',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin, 'superadmin')
            ->post(route('super.construction.projects.tasks-v2.checklists.batch', [
                'project' => $project->id,
                'task' => $task->id,
            ]), [
                'items' => [
                    [
                        'item_title' => 'Verify site boundaries',
                        'day_number' => 1,
                        'sort_order' => 0,
                        'is_completed' => false,
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('task_checklist_items', [
            'task_id' => $task->id,
            'item_title' => 'Verify site boundaries',
        ]);
    }
}
