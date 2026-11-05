<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TaskCreationAndRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private SuperAdmin $superAdmin;
    private Client $client;
    private Project $project;
    private Member $draftPerson;
    private ConstructionRole $draftRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'BETAXTECH Infra',
            'status' => 'active',
        ]);

        $this->superAdmin = SuperAdmin::create([
            'id' => 1,
            'name' => 'Super Admin',
            'email' => 'superadmin@betaxtech.com',
            'username' => 'superadmin',
            'password' => Hash::make('password'),
            'company_id' => $this->company->id,
            'status' => 1,
        ]);

        $this->client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Ajmer Client',
            'client_code' => 'CLI-AJM',
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Ajmer Highway Project',
            'slug' => 'ajmer-highway-project',
            'project_code' => 'PRJ-00009',
            'status' => 'active',
        ]);

        $this->draftPerson = Member::create([
            'name' => 'Draft Person User',
            'email' => 'draftperson@betaxtech.com',
            'username' => 'draftperson',
            'password' => Hash::make('password'),
            'created_by' => $this->superAdmin->id,
            'company_id' => $this->company->id,
            'slug' => 'draft-person',
            'status' => 1,
            'roles' => [],
        ]);

        // Draft Person role without execution task create permissions
        $this->draftRole = ConstructionRole::firstOrCreate(
            ['slug' => 'draft_person'],
            ['name' => 'Draft Person', 'status' => 'active']
        );

        $viewPerm = Permission::firstOrCreate(
            ['slug' => 'project.view'],
            ['name' => 'View Projects', 'module' => 'project']
        );
        $draftingPerm = Permission::firstOrCreate(
            ['slug' => 'drafting.manage'],
            ['name' => 'Manage Drafting', 'module' => 'drafting']
        );
        $this->draftRole->permissions()->sync([$viewPerm->id, $draftingPerm->id]);

        MemberRoleAssignment::create([
            'member_id' => $this->draftPerson->id,
            'role_id' => $this->draftRole->id,
            'project_id' => $this->project->id,
            'status' => 1,
        ]);

        ProjectTeamMember::create([
            'project_id' => $this->project->id,
            'member_id' => $this->draftPerson->id,
            'role_id' => $this->draftRole->id,
            'status' => 'active',
        ]);
    }

    public function test_member_without_task_creation_permission_cannot_create_task(): void
    {
        $response = $this->actingAs($this->draftPerson, 'member')
            ->withSession(['impersonator' => ['guard' => 'superadmin', 'id' => $this->superAdmin->id, 'role' => 'Super Admin']])
            ->post(route('super.construction.projects.tasks.store', $this->project->id), [
                'title' => 'Unauthorized Task Creation',
                'priority' => 'medium',
                'status' => 'draft',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tasks', [
            'title' => 'Unauthorized Task Creation',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_member_with_execution_task_manage_but_without_execution_create_cannot_create_task(): void
    {
        $execTaskManagePerm = Permission::firstOrCreate(
            ['slug' => 'execution_task.manage'],
            ['name' => 'Execution Task Manage', 'module' => 'execution_task']
        );
        $this->draftRole->permissions()->syncWithoutDetaching([$execTaskManagePerm->id]);

        $response = $this->actingAs($this->draftPerson, 'member')
            ->withSession(['impersonator' => ['guard' => 'superadmin', 'id' => $this->superAdmin->id, 'role' => 'Super Admin']])
            ->post(route('super.construction.projects.tasks.store', $this->project->id), [
                'title' => 'Task Should Not Be Created By Manage Alone',
                'priority' => 'medium',
                'status' => 'draft',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tasks', [
            'title' => 'Task Should Not Be Created By Manage Alone',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_member_with_execution_create_permission_can_create_task(): void
    {
        $execCreatePerm = Permission::firstOrCreate(
            ['slug' => 'execution.create'],
            ['name' => 'Create Execution Task', 'module' => 'project_lifecycle']
        );
        $this->draftRole->permissions()->syncWithoutDetaching([$execCreatePerm->id]);

        $response = $this->actingAs($this->draftPerson, 'member')
            ->withSession(['impersonator' => ['guard' => 'superadmin', 'id' => $this->superAdmin->id, 'role' => 'Super Admin']])
            ->post(route('super.construction.projects.tasks.store', $this->project->id), [
                'title' => 'Permitted Task Creation',
                'priority' => 'high',
                'status' => 'planned',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'title' => 'Permitted Task Creation',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_member_without_task_delete_permission_cannot_delete_task(): void
    {
        $task = Task::create([
            'project_id' => $this->project->id,
            'task_code' => 'TSK-DEL-001',
            'title' => 'Task To Be Protected',
            'status' => 'planned',
        ]);

        $response = $this->actingAs($this->draftPerson, 'member')
            ->withSession(['impersonator' => ['guard' => 'superadmin', 'id' => $this->superAdmin->id, 'role' => 'Super Admin']])
            ->delete(route('super.construction.projects.tasks-v2.destroy', [
                'project' => $this->project->id,
                'task' => $task->id,
            ]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_member_with_execution_task_manage_but_without_execution_delete_cannot_delete_task(): void
    {
        $task = Task::create([
            'project_id' => $this->project->id,
            'task_code' => 'TSK-DEL-MAN-001',
            'title' => 'Task To Be Protected From Manage Alone',
            'status' => 'planned',
        ]);

        $execTaskManagePerm = Permission::firstOrCreate(
            ['slug' => 'execution_task.manage'],
            ['name' => 'Execution Task Manage', 'module' => 'execution_task']
        );
        $this->draftRole->permissions()->syncWithoutDetaching([$execTaskManagePerm->id]);

        $response = $this->actingAs($this->draftPerson, 'member')
            ->withSession(['impersonator' => ['guard' => 'superadmin', 'id' => $this->superAdmin->id, 'role' => 'Super Admin']])
            ->delete(route('super.construction.projects.tasks-v2.destroy', [
                'project' => $this->project->id,
                'task' => $task->id,
            ]));

        $response->assertStatus(403);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_member_with_execution_delete_permission_can_delete_task(): void
    {
        $task = Task::create([
            'project_id' => $this->project->id,
            'task_code' => 'TSK-DEL-002',
            'title' => 'Task To Delete',
            'status' => 'planned',
        ]);

        $execDeletePerm = Permission::firstOrCreate(
            ['slug' => 'execution.delete'],
            ['name' => 'Delete Execution Task', 'module' => 'project_lifecycle']
        );
        $this->draftRole->permissions()->syncWithoutDetaching([$execDeletePerm->id]);

        $response = $this->actingAs($this->draftPerson, 'member')
            ->withSession(['impersonator' => ['guard' => 'superadmin', 'id' => $this->superAdmin->id, 'role' => 'Super Admin']])
            ->delete(route('super.construction.projects.tasks-v2.destroy', [
                'project' => $this->project->id,
                'task' => $task->id,
            ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertSoftDeleted('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_company_super_admin_with_draft_person_role_without_task_permission_cannot_create_task(): void
    {
        $companyAdmin = SuperAdmin::create([
            'name' => 'Company Admin',
            'email' => 'companyadmin@betaxtech.com',
            'username' => 'companyadmin',
            'password' => Hash::make('password'),
            'company_id' => $this->company->id,
            'roles' => ['draft_person'],
            'status' => 1,
        ]);

        $response = $this->actingAs($companyAdmin, 'superadmin')
            ->post(route('super.construction.projects.tasks.store', $this->project->id), [
                'title' => 'Company Admin Unauthorized Task',
                'priority' => 'medium',
                'status' => 'draft',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tasks', [
            'title' => 'Company Admin Unauthorized Task',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_inertia_shared_permissions_excludes_task_create_when_active_role_lacks_permission(): void
    {
        $authSvc = app(\App\Services\Construction\ConstructionAuthorizationService::class);
        session(['active_role' => 'draft_person']);
        $perms = $authSvc->permissionsFor($this->draftPerson);

        $this->assertNotContains('execution.create', $perms);
        $this->assertNotContains('execution_task.manage', $perms);
    }
}
