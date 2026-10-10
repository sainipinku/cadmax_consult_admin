<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConstructionAuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

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

    private function createRole(string $slug, string $name = 'Role'): ConstructionRole
    {
        return ConstructionRole::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
            'is_system_role' => true,
            'status' => 'active',
        ]);
    }

    private function createPermission(string $slug): Permission
    {
        return Permission::create([
            'name' => $slug,
            'slug' => $slug,
            'module' => 'test',
        ]);
    }

    private function assignPermission(ConstructionRole $role, Permission $permission): void
    {
        $role->permissions()->attach($permission->id);
    }

    public function test_super_admin_bypasses_all_permission_checks(): void
    {
        $admin = $this->createSuperAdmin();
        $service = app(ConstructionAuthorizationService::class);

        $this->assertTrue($service->hasAnyPermission($admin, ['nonexistent.permission']));
        $this->assertIsArray($service->permissionsFor($admin));
    }

    public function test_admin_member_permissions_follow_assigned_role_permissions(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $member = $this->createMember($superAdmin, 'Admin Member');
        $member->update(['slug' => 'admin']);
        $role = $this->createRole('admin', 'Admin');
        $adminView = $this->createPermission('super_admin.view');
        $projectManage = $this->createPermission('project.manage');
        $this->assignPermission($role, $adminView);
        $this->assignPermission($role, $projectManage);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $role->id,
            'status' => 1,
        ]);

        $service = app(ConstructionAuthorizationService::class);
        $this->assertTrue($service->hasAnyPermission($member, ['super_admin.view']));

        $role->permissions()->detach($adminView->id);

        $this->assertFalse($service->hasAnyPermission($member, ['super_admin.view']));
        $this->assertContains('project.manage', $service->permissionsFor($member));
        $this->assertNotContains('super_admin.view', $service->permissionsFor($member));
    }

    public function test_single_role_member_can_access_own_permissions(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Single Role');
        $project = $this->createProject('PRJ-S1', 'project-s1', 'Single Role Project');
        $role = $this->createRole('surveyor', 'Surveyor');
        $surveyCreate = $this->createPermission('survey.create');
        $this->assignPermission($role, $surveyCreate);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $role->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $service = app(ConstructionAuthorizationService::class);

        $this->assertTrue($service->can($member, 'survey.create', $project));
        $this->assertContains('survey.create', $service->getPermissions($member, $project->id));
    }

    public function test_multi_role_member_resolves_all_active_roles(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Multi Role');
        $project = $this->createProject('PRJ-M1', 'project-m1', 'Multi Role Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);
        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        $service = app(ConstructionAuthorizationService::class);
        $roles = $service->getRoles($member, $project->id);

        $this->assertTrue($roles->contains('slug', 'surveyor'));
        $this->assertTrue($roles->contains('slug', 'vehicle_driver'));
        $this->assertSame(2, $roles->count());
    }

    public function test_role_assignment_must_match_project(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Project Scope');
        $projectA = $this->createProject('PRJ-A', 'project-a', 'Project A');
        $projectB = $this->createProject('PRJ-B', 'project-b', 'Project B');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');
        $tripStart = $this->createPermission('vehicle.trip.start');
        $this->assignPermission($driver, $tripStart);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $projectA->id,
            'status' => 1,
        ]);

        $service = app(ConstructionAuthorizationService::class);

        $this->assertTrue($service->can($member, 'vehicle.trip.start', $projectA));
        $this->assertFalse($service->can($member, 'vehicle.trip.start', $projectB));
    }

    public function test_inactive_member_role_assignment_is_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Inactive Assignment');
        $project = $this->createProject('PRJ-I1', 'project-i1', 'Inactive Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $surveyCreate = $this->createPermission('survey.create');
        $this->assignPermission($surveyor, $surveyCreate);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 0,
        ]);

        $service = app(ConstructionAuthorizationService::class);

        $this->assertFalse($service->can($member, 'survey.create', $project));
        $this->assertTrue($service->getRoles($member, $project->id)->isEmpty());
    }

    public function test_inactive_role_is_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Inactive Role');
        $project = $this->createProject('PRJ-I2', 'project-i2', 'Inactive Role Project');
        $role = $this->createRole('surveyor', 'Surveyor');
        $role->update(['status' => 'inactive']);
        $surveyCreate = $this->createPermission('survey.create');
        $this->assignPermission($role, $surveyCreate);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $role->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        $service = app(ConstructionAuthorizationService::class);

        $this->assertFalse($service->can($member, 'survey.create', $project));
    }

    public function test_deleted_role_is_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Deleted Role');
        $project = $this->createProject('PRJ-D1', 'project-d1', 'Deleted Role Project');
        $role = $this->createRole('surveyor', 'Surveyor');
        $surveyCreate = $this->createPermission('survey.create');
        $this->assignPermission($role, $surveyCreate);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $role->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        $role->delete();

        $service = app(ConstructionAuthorizationService::class);

        $this->assertFalse($service->can($member, 'survey.create', $project));
    }

    public function test_common_permission_model_shared_across_surfaces(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Common Permission Member');
        $project = $this->createProject('PRJ-CP', 'project-cp', 'Common Permission Project');
        $role = $this->createRole('site_employee', 'Site Employee');
        $attendanceMark = $this->createPermission('attendance.mark');
        $this->assignPermission($role, $attendanceMark);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $role->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        $service = app(ConstructionAuthorizationService::class);

        // The same permission is shared for both web and mobile.
        $this->assertTrue($service->can($member, 'attendance.mark', $project));
        $this->assertContains('attendance.mark', $service->getPermissions($member, $project->id));
    }

    public function test_global_role_does_not_grant_unattached_project_access(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Global Role Member');
        $projectA = $this->createProject('PRJ-GA', 'project-ga', 'Global Project A');
        $projectB = $this->createProject('PRJ-GB', 'project-gb', 'Global Project B');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');
        $tripStart = $this->createPermission('vehicle.trip.start');
        $this->assignPermission($driver, $tripStart);

        // Global role assignment (project_id = null).
        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => null,
            'status' => 1,
        ]);

        // Member is attached to project A but NOT project B.
        ProjectTeamMember::create([
            'project_id' => $projectA->id,
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'status' => 'active',
        ]);

        $service = app(ConstructionAuthorizationService::class);

        // Global role must not grant access to an unattached project.
        $this->assertFalse($service->can($member, 'vehicle.trip.start', $projectB));
        // Global role must not grant access even to an attached project for project-scoped checks.
        $this->assertFalse($service->can($member, 'vehicle.trip.start', $projectA));
        // Without project context, global role is visible.
        $this->assertTrue($service->getRoles($member)->contains('slug', 'vehicle_driver'));
    }

    public function test_project_specific_role_requires_exact_project(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Exact Project Member');
        $projectA = $this->createProject('PRJ-EP1', 'project-ep1', 'Exact Project A');
        $projectB = $this->createProject('PRJ-EP2', 'project-ep2', 'Exact Project B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $surveyCreate = $this->createPermission('survey.create');
        $this->assignPermission($surveyor, $surveyCreate);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $projectA->id,
            'status' => 1,
        ]);

        $service = app(ConstructionAuthorizationService::class);

        $this->assertTrue($service->can($member, 'survey.create', $projectA));
        $this->assertFalse($service->can($member, 'survey.create', $projectB));
    }

    public function test_active_team_membership_returns_project(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Active Team Member');
        $project = $this->createProject('PRJ-AT', 'project-at', 'Active Team Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'status' => 'active',
        ]);

        $service = app(ConstructionAuthorizationService::class);
        $projects = $service->getProjects($member);

        $this->assertTrue($projects->contains('id', $project->id));
    }

    public function test_inactive_team_membership_does_not_return_project(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Inactive Team Member');
        $project = $this->createProject('PRJ-IT', 'project-it', 'Inactive Team Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'status' => 'inactive',
        ]);

        $service = app(ConstructionAuthorizationService::class);

        $this->assertFalse($service->getProjects($member)->contains('id', $project->id));
    }

    public function test_resolve_active_role_rejects_unassigned_role(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Role Resolver');
        $project = $this->createProject('PRJ-RR', 'project-rr', 'Resolver Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        $service = app(ConstructionAuthorizationService::class);

        // Single active role on project -> default resolution picks it.
        $this->assertSame('surveyor', $service->resolveActiveRole($member, null, $project->id)?->slug);

        // Requested role is assigned -> allowed.
        $this->assertSame('surveyor', $service->resolveActiveRole($member, 'surveyor', $project->id)?->slug);

        // Requested role is NOT assigned -> rejected.
        $this->assertNull($service->resolveActiveRole($member, 'vehicle_driver', $project->id));

        // Ambiguous (multiple global roles, no project) -> null.
        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => null,
            'status' => 1,
        ]);

        $this->assertNull($service->resolveActiveRole($member, null, null));
    }

    public function test_superadmin_bypasses_all_permission_checks(): void
    {
        $admin = $this->createSuperAdmin();
        $service = app(ConstructionAuthorizationService::class);

        $this->assertTrue($service->hasAnyPermission($admin, ['survey.create']));
        $this->assertTrue($service->hasAnyPermission($admin, ['vehicle.trip.start']));
    }
}