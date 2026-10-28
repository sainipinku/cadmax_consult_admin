<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Role;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminManagementScopingAndRolesTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(string $name): Company
    {
        return Company::create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function createCompanySuperAdmin(Company $company, string $name = 'PCL Admin'): SuperAdmin
    {
        static $seq = 300;
        $seq++;
        return SuperAdmin::create([
            'name' => $name,
            'email' => "pcl_admin_{$seq}@example.com",
            'username' => "pcladmin_{$seq}",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 1,
            'is_project_owner' => 0,
        ]);
    }

    /**
     * Requirement 4:
     * When logging in as PCL Construction super admin:
     * - The PCL Construction super admin must NOT be shown in the Admins list.
     * - Drivers (and other non-admin employees) must NOT be shown in the Admins list.
     * - Only true Admins / Project Admins should show.
     */
    public function test_company_super_admin_does_not_see_themselves_or_drivers_in_admins_list(): void
    {
        $company = $this->createCompany('PCL Construction');
        $superAdmin = $this->createCompanySuperAdmin($company, 'PCL Super Admin');

        // Create Project Admin role and driver role in construction_roles and roles
        $projectAdminConstructionRole = ConstructionRole::create([
            'id' => 1,
            'name' => 'Project Admin',
            'slug' => 'project_admin',
            'status' => 'active',
        ]);
        $driverConstructionRole = ConstructionRole::create([
            'id' => 4,
            'name' => 'Vehicle Driver',
            'slug' => 'vehicle_driver',
            'status' => 'active',
        ]);

        $projectAdminRole = Role::create([
            'id' => 3,
            'name' => 'Project Admin',
            'slug' => 'project_admin',
            'status' => 1,
        ]);
        $driverRole = Role::create([
            'id' => 6,
            'name' => 'Vehicle Driver',
            'slug' => 'vehicle_driver',
            'status' => 1,
        ]);

        // 1. A real Project Admin
        $projectAdminMember = Member::create([
            'name' => 'PCL Project Admin',
            'email' => 'admin.pcl@example.com',
            'username' => 'pcl_project_admin',
            'password' => Hash::make('password'),
            'slug' => 'pcl-project-admin',
            'phone' => '9811111111',
            'status' => 1,
            'roles' => [3],
            'created_by' => $superAdmin->id,
            'company_name' => $company->name,
        ]);
        MemberRoleAssignment::create([
            'member_id' => $projectAdminMember->id,
            'role_id' => $projectAdminConstructionRole->id,
            'status' => 1,
        ]);

        // 2. A driver (should NOT be in admins list)
        $driverMember = Member::create([
            'name' => 'Driver Sohan',
            'email' => 'sohan.driver@example.com',
            'username' => 'driver_sohan',
            'password' => Hash::make('password'),
            'slug' => 'driver-sohan',
            'phone' => '9822222222',
            'status' => 1,
            'roles' => [6],
            'created_by' => $superAdmin->id,
            'company_name' => $company->name,
        ]);
        MemberRoleAssignment::create([
            'member_id' => $driverMember->id,
            'role_id' => $driverConstructionRole->id,
            'status' => 1,
        ]);

        // 3. A synced member record for the Super Admin (should NOT be in admins list)
        $superAdminMember = Member::create([
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'username' => $superAdmin->username,
            'password' => Hash::make('password'),
            'slug' => 'super-admin-member',
            'phone' => '9833333333',
            'status' => 1,
            'roles' => [2], // Super Admin role
            'created_by' => $superAdmin->id,
            'company_name' => $company->name,
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')->get(route('super.super_admins.index'));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/SuperAdmins/Index')
            ->has('admins', 1)
            ->where('admins.0.id', $projectAdminMember->id)
        );
    }

    /**
     * Requirement 5:
     * When creating an Admin, the role is assigned as 'project_admin'.
     */
    public function test_admin_creation_assigns_project_admin_role(): void
    {
        $company = $this->createCompany('PCL Construction');
        $superAdmin = $this->createCompanySuperAdmin($company, 'PCL Super Admin');

        $projectAdminConstructionRole = ConstructionRole::create([
            'id' => 1,
            'name' => 'Project Admin',
            'slug' => 'project_admin',
            'status' => 'active',
        ]);
        $projectAdminRole = Role::create([
            'id' => 3,
            'name' => 'Project Admin',
            'slug' => 'project_admin',
            'status' => 1,
        ]);

        $res = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.super_admins.store_admin'), [
                'name' => 'New Project Admin',
                'email' => 'new.projectadmin@example.com',
                'phone' => '9876543210',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_slug' => 'project_admin',
            ]);

        $res->assertSessionHasNoErrors();

        $createdMember = Member::where('email', 'new.projectadmin@example.com')->first();
        $this->assertNotNull($createdMember);
        $this->assertContains(3, $createdMember->roles);

        $assignment = MemberRoleAssignment::where('member_id', $createdMember->id)->first();
        $this->assertNotNull($assignment);
        $this->assertEquals($projectAdminConstructionRole->id, $assignment->role_id);
    }
}
