<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionAuthorizationService;
use Database\Seeders\Construction\FoundationSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class EquipmentTrackingPermissionTest extends TestCase
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
        static $seq = 100;
        $seq++;

        return SuperAdmin::create([
            'name' => 'Tracking Admin',
            'email' => "trackingadmin{$seq}@cadmax.com",
            'username' => "trackingadmin{$seq}",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 1,
            'is_project_owner' => 0,
        ]);
    }

    public function test_permission_seeders_seed_equipment_tracking_permissions(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->seed(PermissionSeeder::class);

        $viewPerm = Permission::where('slug', 'equipment_tracking.view')->first();
        $this->assertNotNull($viewPerm, 'equipment_tracking.view permission must be seeded');
        $this->assertEquals('Fleet & Equipment', $viewPerm->module);

        $managePerm = Permission::where('slug', 'equipment_tracking.manage')->first();
        $this->assertNotNull($managePerm, 'equipment_tracking.manage permission must be seeded');
        $this->assertEquals('Fleet & Equipment', $managePerm->module);

        $this->seed(SuperAdminPermissionSeeder::class);

        $superAdminRole = ConstructionRole::where('slug', 'super_admin')->first();
        $this->assertNotNull($superAdminRole);

        $this->assertDatabaseHas('construction_role_permissions', [
            'role_id' => $superAdminRole->id,
            'permission_id' => $viewPerm->id,
        ]);

        $this->assertDatabaseHas('construction_role_permissions', [
            'role_id' => $superAdminRole->id,
            'permission_id' => $managePerm->id,
        ]);
    }

    public function test_construction_authorization_service_resolves_equipment_tracking_aliases(): void
    {
        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $admin->id,
            'company_id' => $company->id,
            'name' => 'Equipment Supervisor',
            'username' => 'eq_supervisor',
            'email' => 'eq_supervisor@cadmax.com',
            'phone' => '9888877777',
            'password' => Hash::make('password'),
            'slug' => 'eq-supervisor',
        ]);

        $client = \App\Models\Client::create([
            'company_id' => $company->id,
            'name' => 'Client Bridge',
            'client_code' => 'CLI-BRG-' . rand(100, 999),
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Bridge Construction',
            'slug' => 'bridge-construction-' . rand(100, 999),
            'project_code' => 'PRJ-BRG',
            'status' => 'active',
        ]);

        $role = ConstructionRole::create([
            'name' => 'Fleet Supervisor',
            'slug' => 'fleet_supervisor',
            'status' => 'active',
        ]);

        $managePerm = Permission::firstOrCreate([
            'slug' => 'equipment_tracking.manage',
        ], [
            'name' => 'Manage Equipment Tracking',
            'module' => 'equipment_tracking',
        ]);
        $role->permissions()->syncWithoutDetaching([$managePerm->id]);

        $teamMember = ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => $role->id,
            'status' => 'active',
            'permissions' => ['equipment_tracking.manage'],
        ]);

        $authSvc = app(ConstructionAuthorizationService::class);

        // Managing equipment tracking should grant access to equipment tracking view & equipment allocation
        $this->assertTrue(
            $authSvc->can($member, 'equipment_tracking.view', $project),
            'equipment_tracking.manage should alias equipment_tracking.view'
        );

        $this->assertTrue(
            $authSvc->can($member, 'equipment_allocation.manage', $project),
            'equipment_tracking.manage should alias equipment_allocation.manage'
        );

        $this->assertTrue(
            $authSvc->can($member, 'equipment_usage.manage', $project),
            'equipment_tracking.manage should alias equipment_usage.manage'
        );
    }

    public function test_super_admin_can_access_equipment_tracking_route(): void
    {
        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);

        $response = $this->actingAs($admin, 'superadmin')->get(route('super.construction.equipment.index'));
        $response->assertOk();
    }
}
