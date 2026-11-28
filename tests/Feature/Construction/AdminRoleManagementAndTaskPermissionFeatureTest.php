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
use App\Models\Role;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Services\Construction\CompanyRoleProvisioningService;
use Database\Seeders\Construction\FoundationSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminRoleManagementAndTaskPermissionFeatureTest extends TestCase
{
    use DatabaseTransactions;

    private function seedPermissionsAndBaseRoles(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);

        Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'is_default' => 1, 'status' => 1, 'created_by' => 1]
        );
        Role::firstOrCreate(
            ['slug' => 'project_admin'],
            ['name' => 'Project Admin', 'is_default' => 1, 'status' => 1, 'created_by' => 1]
        );
        Role::firstOrCreate(
            ['slug' => 'surveyor'],
            ['name' => 'Surveyor', 'is_default' => 1, 'status' => 1, 'created_by' => 1]
        );
        Role::firstOrCreate(
            ['slug' => 'site_employee'],
            ['name' => 'Site Employee', 'is_default' => 1, 'status' => 1, 'created_by' => 1]
        );
    }

    private function getOrCreateProjectOwner(): SuperAdmin
    {
        $owner = SuperAdmin::find(1);
        if ($owner) {
            return $owner;
        }

        $owner = new SuperAdmin([
            'uuid' => (string) Str::uuid(),
            'name' => 'Product Owner',
            'email' => 'owner_' . Str::random(6) . '@cadmax.test',
            'username' => 'owner_' . Str::random(6),
            'phone' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => 1,
            'company_id' => null,
        ]);
        $owner->forceFill(['id' => 1])->save();

        return $owner->fresh();
    }

    private function createCompanyWithSuperAdmin(string $label): array
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => "{$label} {$suffix}",
            'status' => 'active',
        ]);

        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => "{$label} SuperAdmin",
            'email' => "sa_{$suffix}@cadmax.test",
            'username' => "sa_{$suffix}",
            'phone' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => 1,
            'company_id' => $company->id,
            'is_project_owner' => 0,
        ]);

        app(CompanyRoleProvisioningService::class)->provisionCompanyRoles($company->id, $superAdmin->id);

        return [$company, $superAdmin];
    }

    private function createCompanyAdminMember(Company $company, SuperAdmin $superAdmin, string $roleSlug = 'project_admin'): Member
    {
        $suffix = Str::lower(Str::random(6));
        $legacyRole = Role::where('slug', $roleSlug)->firstOrFail();
        $companyRole = ConstructionRole::where('company_id', $company->id)
            ->where('slug', $roleSlug)
            ->firstOrFail();

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'company_name' => $company->name,
            'name' => "Admin User {$suffix}",
            'username' => "admin_{$suffix}",
            'slug' => "admin-{$suffix}",
            'email' => "admin_{$suffix}@cadmax.test",
            'phone' => '9' . random_int(100000000, 999999999),
            'status' => Member::STATUS_ACTIVE,
            'roles' => [$legacyRole->id],
            'designation' => ['Admin'],
            'password' => Hash::make('password'),
        ]);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'project_id' => null,
            'role_id' => $companyRole->id,
            'status' => 1,
        ]);

        return $member;
    }

    public function test_project_owner_granting_all_permissions_to_admin_and_project_admin_syncs_to_company_scoped_roles(): void
    {
        $this->seedPermissionsAndBaseRoles();
        $owner = $this->getOrCreateProjectOwner();
        [$companyA, $saA] = $this->createCompanyWithSuperAdmin('GoRun');
        [$companyB, $saB] = $this->createCompanyWithSuperAdmin('BetaCorp');

        $allPermissionIds = Permission::pluck('id')->all();
        $this->assertNotEmpty($allPermissionIds);

        $globalAdminRole = Role::where('slug', 'admin')->firstOrFail();
        $globalProjectAdminRole = Role::where('slug', 'project_admin')->firstOrFail();

        // Simulate Project Owner opening Role Management and checking all checkboxes on Admin & Project Admin
        $this->actingAs($owner, 'superadmin')
            ->post(route('super.role.permissions.update', $globalAdminRole->uuid), [
                'permission_ids' => $allPermissionIds,
            ])
            ->assertRedirect();

        $this->actingAs($owner, 'superadmin')
            ->post(route('super.role.permissions.update', $globalProjectAdminRole->uuid), [
                'permission_ids' => $allPermissionIds,
            ])
            ->assertRedirect();

        foreach ([$companyA->id, $companyB->id] as $cid) {
            foreach (['admin', 'project_admin'] as $slug) {
                $companyRole = ConstructionRole::where('company_id', $cid)
                    ->where('slug', $slug)
                    ->firstOrFail();

                $grantedSlugs = $companyRole->permissions()->pluck('slug')->all();
                $this->assertContains('execution.edit', $grantedSlugs, "{$slug} in company {$cid} must receive execution.edit");
                $this->assertContains('execution.manage', $grantedSlugs, "{$slug} in company {$cid} must receive execution.manage");
                $this->assertContains('execution_task.manage', $grantedSlugs, "{$slug} in company {$cid} must receive execution_task.manage");
                $this->assertContains('role.view', $grantedSlugs, "{$slug} in company {$cid} must receive role.view");
                $this->assertContains('role.manage', $grantedSlugs, "{$slug} in company {$cid} must receive role.manage");
                $this->assertContains('role.assign_permissions', $grantedSlugs, "{$slug} in company {$cid} must receive role.assign_permissions");
            }
        }

        // Verify Company A editing Company A's Project Admin does NOT alter Company B's Project Admin
        $companyAProjectAdminRole = ConstructionRole::where('company_id', $companyA->id)
            ->where('slug', 'project_admin')
            ->firstOrFail();
        $viewOnlyPermId = Permission::where('slug', 'dashboard.view')->value('id');

        $this->actingAs($saA, 'superadmin')
            ->post(route('super.role.permissions.update', $globalProjectAdminRole->uuid), [
                'permission_ids' => [$viewOnlyPermId],
            ])
            ->assertRedirect();
        auth('superadmin')->logout();

        $this->assertEquals([$viewOnlyPermId], $companyAProjectAdminRole->fresh()->permissions()->pluck('construction_permissions.id')->all());

        $companyBProjectAdminRole = ConstructionRole::where('company_id', $companyB->id)
            ->where('slug', 'project_admin')
            ->firstOrFail();
        $this->assertContains('execution.edit', $companyBProjectAdminRole->permissions()->pluck('slug')->all());
    }

    public function test_company_admin_with_role_permissions_can_access_role_management_and_create_company_role_while_unauthorized_gets_403(): void
    {
        $this->seedPermissionsAndBaseRoles();
        $owner = $this->getOrCreateProjectOwner();
        [$companyA, $saA] = $this->createCompanyWithSuperAdmin('GoRun');
        [$companyB, $saB] = $this->createCompanyWithSuperAdmin('BetaCorp');

        // Company B creates an isolated custom role that Company A must never see
        $this->actingAs($saB, 'superadmin')->post(route('super.role.add'), [
            'name' => 'Beta Private Coordinator ' . Str::random(4),
            'description' => 'Beta only',
        ])->assertRedirect();

        // Grant all permissions to global project_admin so Company A's project_admin gets them
        $allPermissionIds = Permission::pluck('id')->all();
        $globalProjectAdminRole = Role::where('slug', 'project_admin')->firstOrFail();
        $this->actingAs($owner, 'superadmin')
            ->post(route('super.role.permissions.update', $globalProjectAdminRole->uuid), [
                'permission_ids' => $allPermissionIds,
            ])
            ->assertRedirect();
        auth('superadmin')->logout();

        $raviAdmin = $this->createCompanyAdminMember($companyA, $saA, 'project_admin');

        // 1. Company Admin (guard: admin) can open Role Management -> 200 OK
        $listResponse = $this->actingAs($raviAdmin, 'admin')->get(route('super.role.list'));
        $listResponse->assertOk();
        $listResponse->assertInertia(function (Assert $page) {
            $page->component('SuperAdmin/Roles/List');
            $props = $page->toArray()['props'] ?? [];
            $roles = collect($props['roles']['data'] ?? $props['roles'] ?? []);
            $perms = collect($props['all_permissions'] ?? $props['permissions'] ?? []);

            $this->assertNull(
                $roles->first(fn ($r) => str_starts_with($r['name'] ?? '', 'Beta Private Coordinator')),
                'Company A Admin must not see Company B custom roles'
            );

            $permSlugs = $perms->pluck('slug')->all();
            $this->assertContains('execution.manage', $permSlugs);
            $this->assertContains('execution_task.manage', $permSlugs);
            $this->assertContains('role.manage', $permSlugs);
            $this->assertContains('super_admin.manage', $permSlugs);
            $this->assertContains('audit_log.view', $permSlugs);
        });

        // 2. Company Admin can create a custom role even if frontend passes Member's id as created_by
        $customRoleName = 'Field Lead ' . Str::random(5);
        $createResponse = $this->actingAs($raviAdmin, 'admin')->post(route('super.role.add'), [
            'name' => $customRoleName,
            'description' => 'Created by Company Admin',
            'created_by' => $raviAdmin->id,
        ]);
        $createResponse->assertSessionHasNoErrors();
        $createResponse->assertRedirect();

        $createdRole = Role::where('name', $customRoleName)->first();
        $this->assertNotNull($createdRole);
        $this->assertEquals($saA->id, $createdRole->super_admin_id);

        // 3. Company Admin can update permissions on the newly created company role
        $execEditPermId = Permission::where('slug', 'execution.edit')->value('id');
        $this->actingAs($raviAdmin, 'admin')
            ->post(route('super.role.permissions.update', $createdRole->uuid), [
                'permission_ids' => [$execEditPermId],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $companyACustomConstructionRole = ConstructionRole::where('company_id', $companyA->id)
            ->where('slug', $createdRole->slug)
            ->first();
        $this->assertNotNull($companyACustomConstructionRole);
        $this->assertContains('execution.edit', $companyACustomConstructionRole->permissions()->pluck('slug')->all());

        // 4. Company Admin with ONLY role.view can open Role Management but gets 403 on add / permission update
        $readOnlyRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Role Viewer Only',
            'slug' => 'role_viewer_' . Str::lower(Str::random(5)),
            'is_default' => 0,
            'status' => 1,
            'super_admin_id' => $saA->id,
            'created_by' => $saA->id,
        ]);
        $readOnlyConstructionRole = ConstructionRole::create([
            'company_id' => $companyA->id,
            'name' => $readOnlyRole->name,
            'slug' => $readOnlyRole->slug,
            'is_system' => false,
            'status' => 'active',
        ]);
        $roleViewId = Permission::where('slug', 'role.view')->value('id');
        $readOnlyConstructionRole->permissions()->sync([$roleViewId]);

        $viewOnlyMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $saA->id,
            'company_name' => $companyA->name,
            'name' => 'Viewer User',
            'username' => 'viewer_' . Str::lower(Str::random(5)),
            'slug' => 'viewer-' . Str::lower(Str::random(5)),
            'email' => 'viewer_' . Str::lower(Str::random(5)) . '@cadmax.test',
            'phone' => '9' . random_int(100000000, 999999999),
            'status' => Member::STATUS_ACTIVE,
            'roles' => [$readOnlyRole->id],
            'password' => Hash::make('password'),
        ]);
        MemberRoleAssignment::create([
            'member_id' => $viewOnlyMember->id,
            'project_id' => null,
            'role_id' => $readOnlyConstructionRole->id,
            'status' => 1,
        ]);

        $this->actingAs($viewOnlyMember, 'admin')->get(route('super.role.list'))->assertOk();
        $this->actingAs($viewOnlyMember, 'admin')->post(route('super.role.add'), ['name' => 'Blocked Create'])->assertForbidden();
        $this->actingAs($viewOnlyMember, 'admin')->post(route('super.role.permissions.update', $createdRole->uuid), ['permission_ids' => [$execEditPermId]])->assertForbidden();

        // 5. Admin without ANY role.* permission gets 403 on Role Management
        $noRolePermRole = ConstructionRole::create([
            'company_id' => $companyA->id,
            'name' => 'Dashboard Only Role',
            'slug' => 'dash_only_' . Str::lower(Str::random(5)),
            'is_system' => false,
            'status' => 'active',
        ]);
        $dashViewId = Permission::where('slug', 'dashboard.view')->value('id');
        $noRolePermRole->permissions()->sync([$dashViewId]);

        $noRoleMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $saA->id,
            'company_name' => $companyA->name,
            'name' => 'No Role Access',
            'username' => 'norole_' . Str::lower(Str::random(5)),
            'slug' => 'norole-' . Str::lower(Str::random(5)),
            'email' => 'norole_' . Str::lower(Str::random(5)) . '@cadmax.test',
            'phone' => '9' . random_int(100000000, 999999999),
            'status' => Member::STATUS_ACTIVE,
            'roles' => [],
            'password' => Hash::make('password'),
        ]);
        MemberRoleAssignment::create([
            'member_id' => $noRoleMember->id,
            'project_id' => null,
            'role_id' => $noRolePermRole->id,
            'status' => 1,
        ]);

        $this->actingAs($noRoleMember, 'admin')->get(route('super.role.list'))->assertForbidden();

        // 6. Product SuperAdmin without company_id (id != 1, is_project_owner != 1) still gets 403
        auth('admin')->logout();
        auth('superadmin')->logout();
        $unscopedSuperAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Unscoped Super',
            'email' => 'unscoped_' . Str::lower(Str::random(5)) . '@cadmax.test',
            'username' => 'unscoped_' . Str::lower(Str::random(5)),
            'phone' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => 1,
            'company_id' => null,
            'is_project_owner' => 0,
        ]);
        $this->actingAs($unscopedSuperAdmin, 'superadmin')->get(route('super.role.list'))->assertForbidden();
    }

    public function test_company_admin_and_manage_permission_holders_can_unassign_and_transfer_execution_tasks(): void
    {
        $this->seedPermissionsAndBaseRoles();
        $owner = $this->getOrCreateProjectOwner();
        [$company, $sa] = $this->createCompanyWithSuperAdmin('GoRun');

        $allPermissionIds = Permission::pluck('id')->all();
        $globalAdminRole = Role::where('slug', 'admin')->firstOrFail();
        $globalProjectAdminRole = Role::where('slug', 'project_admin')->firstOrFail();
        $this->actingAs($owner, 'superadmin')
            ->post(route('super.role.permissions.update', $globalAdminRole->uuid), [
                'permission_ids' => $allPermissionIds,
            ])
            ->assertRedirect();
        $this->actingAs($owner, 'superadmin')
            ->post(route('super.role.permissions.update', $globalProjectAdminRole->uuid), [
                'permission_ids' => $allPermissionIds,
            ])
            ->assertRedirect();
        auth('superadmin')->logout();

        $raviAdmin = $this->createCompanyAdminMember($company, $sa, 'admin');

        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Metro Client',
            'client_code' => 'CLI-' . random_int(1000, 9999),
            'status' => 'active',
        ]);
        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Chomu Float Side Map Survey',
            'slug' => 'chomu-survey-' . Str::lower(Str::random(5)),
            'project_code' => 'PRJ-' . random_int(1000, 9999),
            'status' => 'active',
        ]);

        $assignee1 = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $sa->id,
            'company_name' => $company->name,
            'name' => 'Abhishek Tiwari',
            'username' => 'abhishek_' . Str::lower(Str::random(5)),
            'slug' => 'abhishek-' . Str::lower(Str::random(5)),
            'email' => 'abhishek_' . Str::lower(Str::random(5)) . '@cadmax.test',
            'phone' => '9' . random_int(100000000, 999999999),
            'status' => Member::STATUS_ACTIVE,
            'password' => Hash::make('password'),
        ]);
        $assignee2 = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $sa->id,
            'company_name' => $company->name,
            'name' => 'Anil Yadav',
            'username' => 'anil_' . Str::lower(Str::random(5)),
            'slug' => 'anil-' . Str::lower(Str::random(5)),
            'email' => 'anil_' . Str::lower(Str::random(5)) . '@cadmax.test',
            'phone' => '9' . random_int(100000000, 999999999),
            'status' => Member::STATUS_ACTIVE,
            'password' => Hash::make('password'),
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-' . random_int(10000, 99999),
            'title' => 'survey site plan',
            'task_source' => 'admin_created',
            'task_type' => 'one_time',
            'status' => 'in_progress',
            'priority' => 'medium',
            'sort_order' => 1,
        ]);

        $workerRole = ConstructionRole::where('company_id', $company->id)
            ->whereIn('slug', ['surveyor', 'site_employee'])
            ->first()
            ?? ConstructionRole::whereIn('slug', ['surveyor', 'site_employee'])->firstOrFail();

        MemberRoleAssignment::create([
            'member_id' => $assignee1->id,
            'project_id' => $project->id,
            'role_id' => $workerRole->id,
            'status' => 1,
        ]);
        MemberRoleAssignment::create([
            'member_id' => $assignee2->id,
            'project_id' => $project->id,
            'role_id' => $workerRole->id,
            'status' => 1,
        ]);
        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $assignee1->id,
            'role_id' => $workerRole->id,
            'status' => 'active',
        ]);
        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $assignee2->id,
            'role_id' => $workerRole->id,
            'status' => 'active',
        ]);

        TaskAssignment::create([
            'task_id' => $task->id,
            'project_id' => $project->id,
            'assigned_to' => $assignee1->id,
            'assignment_role' => 'executor',
            'is_primary' => true,
            'status' => 'active',
            'assigned_by' => $sa->id,
        ]);

        // 1. Company Admin clicks "Remove Task Assignment -> Yes, Remove Assignment"
        $unassignResponse = $this->actingAs($raviAdmin, 'admin')
            ->post(route('super.construction.projects.tasks-v2.unassign', [$project->id, $task->id]), [
                'task_source' => 'unified',
                'unified_task_id' => $task->id,
            ]);
        $unassignResponse->assertSessionHasNoErrors();
        $unassignResponse->assertRedirect();
        $this->assertFalse(
            TaskAssignment::where('task_id', $task->id)
                ->whereNull('deleted_at')
                ->exists(),
            'TaskAssignment should be deactivated after Admin unassigns task'
        );

        // 2. Company Admin clicks "Assign / Transfer Task" to assign to Anil Yadav
        $transferResponse = $this->actingAs($raviAdmin, 'admin')
            ->post(route('super.construction.projects.tasks-v2.transfer', [$project->id, $task->id]), [
                'member_id' => $assignee2->id,
                'instructions' => 'Handle survey site plan',
            ]);
        $transferResponse->assertSessionHasNoErrors();
        $transferResponse->assertRedirect();
        $this->assertTrue(
            TaskAssignment::where('task_id', $task->id)
                ->where('assigned_to', $assignee2->id)
                ->whereNull('deleted_at')
                ->exists(),
            'TaskAssignment should assign to Anil Yadav after Admin transfers task'
        );

        // 3. Role granted ONLY execution.manage (not execution.edit directly) can still unassign and transfer
        auth('admin')->logout();
        $execManageOnlyRole = ConstructionRole::create([
            'company_id' => $company->id,
            'name' => 'Execution Manager Custom',
            'slug' => 'exec_mgr_' . Str::lower(Str::random(5)),
            'is_system' => false,
            'status' => 'active',
        ]);
        $execManagePermId = Permission::where('slug', 'execution.manage')->value('id');
        $this->assertNotNull($execManagePermId, 'execution.manage permission must be seeded');
        $execManageOnlyRole->permissions()->sync([$execManagePermId]);

        $execManagerMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $sa->id,
            'company_name' => $company->name,
            'name' => 'Exec Manager',
            'username' => 'execmgr_' . Str::lower(Str::random(5)),
            'slug' => 'execmgr-' . Str::lower(Str::random(5)),
            'email' => 'execmgr_' . Str::lower(Str::random(5)) . '@cadmax.test',
            'phone' => '9' . random_int(100000000, 999999999),
            'status' => Member::STATUS_ACTIVE,
            'password' => Hash::make('password'),
        ]);
        MemberRoleAssignment::create([
            'member_id' => $execManagerMember->id,
            'project_id' => $project->id,
            'role_id' => $execManageOnlyRole->id,
            'status' => 1,
        ]);

        $this->actingAs($execManagerMember, 'admin')
            ->post(route('super.construction.projects.tasks-v2.unassign', [$project->id, $task->id]), [
                'task_source' => 'unified',
                'unified_task_id' => $task->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($execManagerMember, 'admin')
            ->post(route('super.construction.projects.tasks-v2.transfer', [$project->id, $task->id]), [
                'member_id' => $assignee1->id,
                'instructions' => 'Reassigned by execution manager',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertTrue(
            TaskAssignment::where('task_id', $task->id)
                ->where('assigned_to', $assignee1->id)
                ->whereNull('deleted_at')
                ->exists()
        );

        // 4. Member without execution permissions is still denied (403)
        auth('admin')->logout();
        $dashViewId = Permission::where('slug', 'dashboard.view')->value('id');
        $restrictedRole = ConstructionRole::create([
            'company_id' => $company->id,
            'name' => 'Restricted Worker',
            'slug' => 'restricted_' . Str::lower(Str::random(5)),
            'is_system' => false,
            'status' => 'active',
        ]);
        $restrictedRole->permissions()->sync([$dashViewId]);

        $workerMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $sa->id,
            'company_name' => $company->name,
            'name' => 'Plain Worker',
            'username' => 'worker_' . Str::lower(Str::random(5)),
            'slug' => 'worker-' . Str::lower(Str::random(5)),
            'email' => 'worker_' . Str::lower(Str::random(5)) . '@cadmax.test',
            'phone' => '9' . random_int(100000000, 999999999),
            'status' => Member::STATUS_ACTIVE,
            'password' => Hash::make('password'),
        ]);
        MemberRoleAssignment::create([
            'member_id' => $workerMember->id,
            'project_id' => $project->id,
            'role_id' => $restrictedRole->id,
            'status' => 1,
        ]);

        $this->actingAs($workerMember, 'admin')
            ->post(route('super.construction.projects.tasks-v2.transfer', [$project->id, $task->id]), [
                'member_id' => $assignee2->id,
            ])
            ->assertForbidden();
    }
}
