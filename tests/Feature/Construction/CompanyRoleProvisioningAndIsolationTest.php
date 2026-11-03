<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionAuthorizationService;
use App\Services\Construction\CompanyRoleProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanyRoleProvisioningAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $platformOwner;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions
        Permission::create(['name' => 'Create Execution Task', 'slug' => 'execution.create', 'module' => 'execution_task']);
        Permission::create(['name' => 'View Execution Task', 'slug' => 'execution.task.view', 'module' => 'execution_task']);
        Permission::create(['name' => 'Create Project', 'slug' => 'project.create', 'module' => 'project_lifecycle']);
        Permission::create(['name' => 'Manage Roles', 'slug' => 'role.manage', 'module' => 'role_management']);
        Permission::create(['name' => 'Assign Permissions', 'slug' => 'role.assign_permissions', 'module' => 'role_management']);

        // Seed global template construction roles (company_id = NULL)
        $templates = [
            ['slug' => 'project_admin', 'name' => 'Project Admin', 'is_system_role' => true],
            ['slug' => 'surveyor', 'name' => 'Surveyor', 'is_system_role' => true],
            ['slug' => 'draft_person', 'name' => 'Draft Person', 'is_system_role' => true],
            ['slug' => 'vehicle_driver', 'name' => 'Vehicle Driver', 'is_system_role' => true],
            ['slug' => 'review_approver', 'name' => 'Review Approver', 'is_system_role' => true],
            ['slug' => 'site_employee', 'name' => 'Site Employee', 'is_system_role' => true],
            ['slug' => 'super_admin', 'name' => 'Super Admin', 'is_system_role' => true],
            ['slug' => 'admin', 'name' => 'Admin', 'is_system_role' => false],
        ];

        foreach ($templates as $t) {
            $cr = ConstructionRole::create([
                'company_id' => null,
                'slug' => $t['slug'],
                'name' => $t['name'],
                'is_system_role' => $t['is_system_role'],
                'status' => 'active',
            ]);

            // Give all permissions to super_admin, and execution permissions to draft_person
            if ($t['slug'] === 'super_admin') {
                $allPermIds = Permission::pluck('id')->all();
                $cr->permissions()->sync($allPermIds);
            } elseif ($t['slug'] === 'draft_person') {
                $execPermIds = Permission::whereIn('slug', ['execution.create', 'execution.task.view', 'project.create'])->pluck('id')->all();
                $cr->permissions()->sync($execPermIds);
            }

            // Sync with Role table
            Role::create([
                'slug' => $t['slug'],
                'name' => $t['name'],
                'status' => 1,
                'is_default' => 1,
                'created_by' => 1,
            ]);
        }

        // Platform owner (SuperAdmin ID 1)
        $this->platformOwner = SuperAdmin::create([
            'name' => 'Root Admin',
            'email' => 'root@cadmax.com',
            'username' => 'rootadmin',
            'password' => Hash::make('password123'),
            'company_id' => null,
            'roles' => ['super_admin', 'project_owner'],
            'status' => 1,
        ]);
    }

    public function test_super_admin_creation_auto_assigns_super_admin_role_and_provisions_company_roles(): void
    {
        $payload = [
            'name' => 'Alpha SuperAdmin',
            'email' => 'alpha@company.com',
            'username' => 'alphasuper',
            'phone' => '9876543210',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Alpha Constructions',
            'company_legal_name' => 'Alpha Constructions Pvt Ltd',
            'company_email' => 'contact@alpha.com',
            'company_phone' => '9876543211',
        ];

        $response = $this->actingAs($this->platformOwner, 'superadmin')
            ->post(route('super.super_admins.store_superadmin'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $superAdmin = SuperAdmin::where('email', 'alpha@company.com')->first();
        $this->assertNotNull($superAdmin);
        $this->assertNotNull($superAdmin->company_id);
        
        // 1. Roles array contains 'super_admin'
        $this->assertContains('super_admin', (array)$superAdmin->roles);

        // 2. Company roles provisioned in construction_roles with company_id
        $companyRoles = ConstructionRole::where('company_id', $superAdmin->company_id)->get();
        $this->assertCount(8, $companyRoles);

        $draftPersonRole = $companyRoles->firstWhere('slug', 'draft_person');
        $this->assertNotNull($draftPersonRole);
        $this->assertEquals($superAdmin->company_id, $draftPersonRole->company_id);

        // 3. Permissions cloned into construction_role_permissions
        $assignedPermSlugs = DB::table('construction_role_permissions')
            ->join('construction_permissions', 'construction_permissions.id', '=', 'construction_role_permissions.permission_id')
            ->where('construction_role_permissions.role_id', $draftPersonRole->id)
            ->pluck('construction_permissions.slug')
            ->toArray();

        $this->assertContains('execution.create', $assignedPermSlugs);
        $this->assertContains('execution.task.view', $assignedPermSlugs);
    }

    public function test_updating_role_permissions_in_company_only_affects_that_company(): void
    {
        $provisioner = app(CompanyRoleProvisioningService::class);

        $companyA = Company::create(['name' => 'Company A', 'status' => 'active']);
        $provisioner->provisionCompanyRoles($companyA->id);

        $companyB = Company::create(['name' => 'Company B', 'status' => 'active']);
        $provisioner->provisionCompanyRoles($companyB->id);

        $superAdminA = SuperAdmin::create([
            'name' => 'SuperAdmin A',
            'email' => 'admin@companya.com',
            'password' => Hash::make('password'),
            'company_id' => $companyA->id,
            'roles' => ['super_admin'],
            'status' => 1,
        ]);

        $roleDraftPerson = Role::where('slug', 'draft_person')->first();
        $this->assertNotNull($roleDraftPerson);

        // Company A removes 'execution.create', only keeps 'execution.task.view'
        $viewPerm = Permission::where('slug', 'execution.task.view')->first();

        $response = $this->actingAs($superAdminA, 'superadmin')
            ->post(route('super.role.permissions.update', $roleDraftPerson->uuid), [
                'permission_ids' => [$viewPerm->id],
            ]);

        $response->assertRedirect();

        // Check Company A's draft_person role in construction_roles
        $cRoleA = ConstructionRole::where('company_id', $companyA->id)->where('slug', 'draft_person')->first();
        $cRoleB = ConstructionRole::where('company_id', $companyB->id)->where('slug', 'draft_person')->first();
        $templateRole = ConstructionRole::whereNull('company_id')->where('slug', 'draft_person')->first();

        $permsA = DB::table('construction_role_permissions')->where('role_id', $cRoleA->id)->pluck('permission_id')->toArray();
        $permsB = DB::table('construction_role_permissions')->where('role_id', $cRoleB->id)->pluck('permission_id')->toArray();
        $permsTemplate = DB::table('construction_role_permissions')->where('role_id', $templateRole->id)->pluck('permission_id')->toArray();

        $createPerm = Permission::where('slug', 'execution.create')->first();

        // Company A does NOT have execution.create
        $this->assertNotContains($createPerm->id, $permsA);
        $this->assertContains($viewPerm->id, $permsA);

        // Company B and Template STILL have execution.create
        $this->assertContains($createPerm->id, $permsB);
        $this->assertContains($createPerm->id, $permsTemplate);
    }

    public function test_member_created_and_updated_gets_assigned_company_scoped_role_id(): void
    {
        $provisioner = app(CompanyRoleProvisioningService::class);
        $company = Company::create(['name' => 'Omega Infra', 'status' => 'active']);
        $provisioner->provisionCompanyRoles($company->id);

        $superAdmin = SuperAdmin::create([
            'name' => 'Omega SuperAdmin',
            'email' => 'sa@omega.com',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'roles' => ['super_admin'],
            'status' => 1,
        ]);

        $companyDraftRole = ConstructionRole::where('company_id', $company->id)->where('slug', 'draft_person')->first();
        $this->assertNotNull($companyDraftRole);

        // Create Admin with draft_person role
        $payload = [
            'name' => 'Draft Man Omega',
            'email' => 'draft@omega.com',
            'phone' => '9111122222',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role_slug' => 'draft_person',
        ];

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.super_admins.store_admin'), $payload);

        $response->assertRedirect();

        $member = Member::where('email', 'draft@omega.com')->first();
        $this->assertNotNull($member);

        // Verify assignment in construction_member_role_assignments is Company Draft Role ID
        $assignment = DB::table('construction_member_role_assignments')
            ->where('member_id', $member->id)
            ->where('status', 1)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertEquals($companyDraftRole->id, $assignment->role_id);
    }

    public function test_authorization_service_resolves_isolated_company_permissions(): void
    {
        $provisioner = app(CompanyRoleProvisioningService::class);
        $companyA = Company::create(['name' => 'Company A', 'status' => 'active']);
        $provisioner->provisionCompanyRoles($companyA->id);

        $companyB = Company::create(['name' => 'Company B', 'status' => 'active']);
        $provisioner->provisionCompanyRoles($companyB->id);

        $cRoleA = ConstructionRole::where('company_id', $companyA->id)->where('slug', 'draft_person')->first();
        $cRoleB = ConstructionRole::where('company_id', $companyB->id)->where('slug', 'draft_person')->first();

        // Company A removes execution.create
        $createPerm = Permission::where('slug', 'execution.create')->first();
        DB::table('construction_role_permissions')
            ->where('role_id', $cRoleA->id)
            ->where('permission_id', $createPerm->id)
            ->delete();

        $memberA = Member::create([
            'name' => 'Member A',
            'email' => 'membera@companya.com',
            'username' => 'member_a',
            'slug' => 'member-a',
            'password' => Hash::make('password'),
            'company_name' => $companyA->name,
            'roles' => ['draft_person'],
            'status' => 1,
            'created_by' => 1,
        ]);
        DB::table('construction_member_role_assignments')->insert([
            'member_id' => $memberA->id,
            'role_id' => $cRoleA->id,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberB = Member::create([
            'name' => 'Member B',
            'email' => 'memberb@companyb.com',
            'username' => 'member_b',
            'slug' => 'member-b',
            'password' => Hash::make('password'),
            'company_name' => $companyB->name,
            'roles' => ['draft_person'],
            'status' => 1,
            'created_by' => 1,
        ]);
        DB::table('construction_member_role_assignments')->insert([
            'member_id' => $memberB->id,
            'role_id' => $cRoleB->id,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $authService = app(ConstructionAuthorizationService::class);

        $permsA = $authService->permissionsFor($memberA);
        $permsB = $authService->permissionsFor($memberB);

        $this->assertNotContains('execution.create', $permsA);
        $this->assertContains('execution.create', $permsB);
    }

    public function test_roles_controller_list_shows_company_scoped_permissions(): void
    {
        $provisioner = app(CompanyRoleProvisioningService::class);
        $companyA = Company::create(['name' => 'Company A', 'status' => 'active']);
        $provisioner->provisionCompanyRoles($companyA->id);

        $companyB = Company::create(['name' => 'Company B', 'status' => 'active']);
        $provisioner->provisionCompanyRoles($companyB->id);

        $superAdminA = SuperAdmin::create([
            'name' => 'SuperAdmin A',
            'email' => 'sa@companya.com',
            'password' => Hash::make('password'),
            'company_id' => $companyA->id,
            'roles' => ['super_admin'],
            'status' => 1,
        ]);

        $superAdminB = SuperAdmin::create([
            'name' => 'SuperAdmin B',
            'email' => 'sa@companyb.com',
            'password' => Hash::make('password'),
            'company_id' => $companyB->id,
            'roles' => ['super_admin'],
            'status' => 1,
        ]);

        $cRoleA = ConstructionRole::where('company_id', $companyA->id)->where('slug', 'draft_person')->first();
        $createPerm = Permission::where('slug', 'execution.create')->first();

        // Company A removes execution.create
        DB::table('construction_role_permissions')
            ->where('role_id', $cRoleA->id)
            ->where('permission_id', $createPerm->id)
            ->delete();

        // SuperAdmin A visits Roles list
        $responseA = $this->actingAs($superAdminA, 'superadmin')
            ->get(route('super.role.list'));
        $responseA->assertOk();

        $rolesA = $responseA->original->getData()['page']['props']['roles']['data'];
        $draftPersonA = collect($rolesA)->firstWhere('slug', 'draft_person');
        $this->assertNotNull($draftPersonA);
        $this->assertNotContains($createPerm->id, $draftPersonA['permissions']);

        // SuperAdmin B visits Roles list
        $responseB = $this->actingAs($superAdminB, 'superadmin')
            ->get(route('super.role.list'));
        $responseB->assertOk();

        $rolesB = $responseB->original->getData()['page']['props']['roles']['data'];
        $draftPersonB = collect($rolesB)->firstWhere('slug', 'draft_person');
        $this->assertNotNull($draftPersonB);
        $this->assertContains($createPerm->id, $draftPersonB['permissions']);
    }
}
