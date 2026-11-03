<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\ExecutionTask;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Services\Construction\ConstructionAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberRolePermissionAndProjectScopingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private SuperAdmin $superAdmin;
    private Client $client;
    private Member $draftPerson;
    private ConstructionRole $draftRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'BETAXTECH',
            'status' => 'active',
        ]);

        $this->superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@betaxtech.com',
            'username' => 'superadmin',
            'password' => Hash::make('password'),
            'company_id' => $this->company->id,
            'status' => 1,
        ]);

        $this->client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Govt Client',
            'client_code' => 'CLI-001',
            'status' => 'active',
        ]);

        $this->draftPerson = Member::create([
            'name' => 'Vikas Sain',
            'email' => 'vikassain@gmail.com',
            'username' => 'vikassain',
            'password' => Hash::make('password'),
            'created_by' => $this->superAdmin->id,
            'company_id' => $this->company->id,
            'slug' => 'draft-person',
            'status' => 1,
            'roles' => [],
        ]);

        $this->draftRole = ConstructionRole::firstOrCreate(
            ['slug' => 'draft_person'],
            ['name' => 'Draft Person', 'status' => 'active']
        );

        // Assign draft_person role to member
        MemberRoleAssignment::create([
            'member_id' => $this->draftPerson->id,
            'role_id' => $this->draftRole->id,
            'status' => 1,
        ]);
    }

    public function test_member_without_project_create_permission_cannot_create_project_and_does_not_receive_permission(): void
    {
        // Grant project.view and project.edit, but explicitly ensure project.create is NOT granted to the role
        $viewPerm = Permission::firstOrCreate(['slug' => 'project.view'], ['name' => 'View Projects', 'module' => 'Project Lifecycle']);
        $editPerm = Permission::firstOrCreate(['slug' => 'project.edit'], ['name' => 'Edit Project', 'module' => 'Project Lifecycle']);
        $createPerm = Permission::firstOrCreate(['slug' => 'project.create'], ['name' => 'Create Project', 'module' => 'Project Lifecycle']);

        // Remove project.create from this role in case seeded
        DB::table('construction_role_permissions')
            ->where('role_id', $this->draftRole->id)
            ->where('permission_id', $createPerm->id)
            ->delete();

        DB::table('construction_role_permissions')->insertOrIgnore([
            ['role_id' => $this->draftRole->id, 'permission_id' => $viewPerm->id],
            ['role_id' => $this->draftRole->id, 'permission_id' => $editPerm->id],
        ]);

        /** @var ConstructionAuthorizationService $authSvc */
        $authSvc = app(ConstructionAuthorizationService::class);
        $perms = $authSvc->permissionsFor($this->draftPerson);

        $this->assertContains('project.view', $perms);
        $this->assertContains('project.edit', $perms);
        $this->assertNotContains('project.create', $perms);

        // Attempting to post to project store route must be rejected with 403 Forbidden
        $response = $this->actingAs($this->draftPerson, 'member')
            ->withSession(['impersonator' => ['guard' => 'superadmin', 'id' => $this->superAdmin->id, 'role' => 'Super Admin']])
            ->post(route('super.construction.projects.store'), [
                'company_id' => $this->company->id,
                'client_id' => $this->client->id,
                'name' => 'Unauthorized New Project',
                'project_code' => 'PRJ-UNAUTH',
            ]);

        $response->assertStatus(403);
    }

    public function test_regular_member_only_sees_projects_they_are_assigned_to(): void
    {
        // Create 2 projects for the same company
        $assignedProject = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Chomu Mhana',
            'slug' => 'chomu-mhana',
            'project_code' => 'PRJ-00003',
            'status' => 'active',
        ]);

        $unassignedProject = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Ajmer Highway Project',
            'slug' => 'ajmer-highway-project',
            'project_code' => 'PRJ-00009',
            'status' => 'active',
        ]);

        // Explicitly assign draftPerson only to Project 1 (Chomu Mhana)
        ProjectTeamMember::create([
            'project_id' => $assignedProject->id,
            'member_id' => $this->draftPerson->id,
            'role_id' => $this->draftRole->id,
            'status' => 'active',
        ]);

        // Grant project.view
        $viewPerm = Permission::firstOrCreate(['slug' => 'project.view'], ['name' => 'View Projects', 'module' => 'Project Lifecycle']);
        DB::table('construction_role_permissions')->insertOrIgnore([
            ['role_id' => $this->draftRole->id, 'permission_id' => $viewPerm->id],
        ]);

        // Test in emulated SuperAdmin workspace (like in user screenshot)
        $response = $this->actingAs($this->draftPerson, 'member')
            ->withSession(['impersonator' => ['guard' => 'superadmin', 'id' => $this->superAdmin->id, 'role' => 'Super Admin']])
            ->get(route('super.construction.projects.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Construction/Projects/Index')
            ->has('projects', 1)
            ->where('projects.0.id', $assignedProject->id)
            ->where('projects.0.name', 'Chomu Mhana')
        );

        // Also test in member portal route
        $memberResponse = $this->actingAs($this->draftPerson, 'member')
            ->get(route('member.construction.projects.index'));
        $memberResponse->assertOk();
        $memberResponse->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $assignedProject->id)
        );
    }

    public function test_regular_member_with_task_assignment_can_see_project_even_without_team_record(): void
    {
        $taskProject = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Metro Line Execution',
            'slug' => 'metro-line-execution',
            'project_code' => 'PRJ-00055',
            'status' => 'active',
        ]);

        $otherProject = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Unrelated Bridge Project',
            'slug' => 'unrelated-bridge-project',
            'project_code' => 'PRJ-00056',
            'status' => 'active',
        ]);

        // Assign Task to member on taskProject
        Task::create([
            'project_id' => $taskProject->id,
            'member_id' => $this->draftPerson->id,
            'title' => 'Prepare 2D CAD Layout',
            'status' => 'in_progress',
        ]);

        // Grant project.view
        $viewPerm = Permission::firstOrCreate(['slug' => 'project.view'], ['name' => 'View Projects', 'module' => 'Project Lifecycle']);
        DB::table('construction_role_permissions')->insertOrIgnore([
            ['role_id' => $this->draftRole->id, 'permission_id' => $viewPerm->id],
        ]);

        $response = $this->actingAs($this->draftPerson, 'member')
            ->withSession(['impersonator' => ['guard' => 'superadmin', 'id' => $this->superAdmin->id, 'role' => 'Super Admin']])
            ->get(route('super.construction.projects.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $taskProject->id)
        );
    }

    public function test_regular_member_cannot_access_unassigned_project_show_page(): void
    {
        $unassignedProject = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Restricted Project',
            'slug' => 'restricted-project',
            'project_code' => 'PRJ-RESTRICT',
            'status' => 'active',
        ]);

        $viewPerm = Permission::firstOrCreate(['slug' => 'project.view'], ['name' => 'View Projects', 'module' => 'Project Lifecycle']);
        DB::table('construction_role_permissions')->insertOrIgnore([
            ['role_id' => $this->draftRole->id, 'permission_id' => $viewPerm->id],
        ]);

        $response = $this->actingAs($this->draftPerson, 'member')
            ->withSession(['impersonator' => ['guard' => 'superadmin', 'id' => $this->superAdmin->id, 'role' => 'Super Admin']])
            ->get(route('super.construction.projects.show', $unassignedProject->id));

        $response->assertStatus(403);
    }
}
