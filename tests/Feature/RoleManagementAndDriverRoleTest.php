<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Member;
use App\Models\Role;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleManagementAndDriverRoleTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(string $email, ?Company $company = null): SuperAdmin
    {
        $comp = $company ?: Company::create(['name' => 'Test Company ' . Str::random(4), 'status' => 'active']);

        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin ' . Str::random(4),
            'email' => $email,
            'phone' => '9' . random_int(100000000, 999999999),
            'whatsapp_phone' => '9' . random_int(100000000, 999999999),
            'password' => 'password',
            'status' => 1,
            'company_id' => $comp->id,
        ]);
    }

    private function assertFlashedMessage(string $type, ?string $messageSubstring = null): void
    {
        $envelopes = session('flasher::envelopes', []);
        $this->assertNotEmpty($envelopes, "Expected flash notifications in session, but found none.");

        $matched = false;
        foreach ($envelopes as $envelope) {
            if (is_object($envelope) && method_exists($envelope, 'getNotification')) {
                $notification = $envelope->getNotification();
                $envType = $notification->getType();
                $envMsg = $notification->getMessage();
                if ($envType === $type && ($messageSubstring === null || str_contains($envMsg, $messageSubstring))) {
                    $matched = true;
                    break;
                }
            } else {
                $str = is_string($envelope) ? $envelope : serialize($envelope);
                if (str_contains($str, $type) && ($messageSubstring === null || str_contains($str, $messageSubstring))) {
                    $matched = true;
                    break;
                }
            }
        }


        $this->assertTrue($matched, "Expected flash of type [{$type}] containing '{$messageSubstring}'.");
    }

    public function test_default_roles_cannot_be_edited_or_deleted(): void
    {
        // 1. Ensure default roles exist
        $driverRole = Role::firstOrCreate(
            ['slug' => 'vehicle_driver'],
            ['name' => 'Vehicle Driver', 'is_default' => 1, 'super_admin_id' => null, 'status' => 1, 'created_by' => 1]
        );

        $superAdmin = $this->createSuperAdmin('owner@example.com');

        // Attempt to edit default role
        $response = $this->actingAs($superAdmin, 'superadmin')
            ->put("/super/role/{$driverRole->uuid}/update", [
                'name' => 'Modified Driver Name',
            ]);

        $response->assertRedirect();
        $this->assertFlashedMessage('error', 'Default system roles cannot be edited.');
        $this->assertEquals('Vehicle Driver', $driverRole->fresh()->name);

        // Attempt to delete default role
        $deleteResponse = $this->actingAs($superAdmin, 'superadmin')
            ->delete("/super/role/{$driverRole->uuid}");

        $deleteResponse->assertRedirect();
        $this->assertFlashedMessage('error', 'Default system roles cannot be deleted.');
        $this->assertNull($driverRole->fresh()->deleted_at);
    }

    public function test_custom_role_is_associated_with_creating_super_admin_and_scoped_properly(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'status' => 'active']);
        $companyB = Company::create(['name' => 'Company B', 'status' => 'active']);

        $superAdminA = $this->createSuperAdmin('adminA@example.com', $companyA);
        $superAdminB = $this->createSuperAdmin('adminB@example.com', $companyB);

        // SuperAdmin A creates a custom role
        $response = $this->actingAs($superAdminA, 'superadmin')->post('/super/role/add', [
            'name' => 'Plumber Lead',
        ]);

        $response->assertRedirect();
        $this->assertFlashedMessage('success', 'Role created successfully!');

        $createdRole = Role::where('name', 'Plumber Lead')->first();
        $this->assertNotNull($createdRole);
        $this->assertEquals($superAdminA->id, $createdRole->super_admin_id);
        $this->assertFalse((bool) $createdRole->is_default);

        // SuperAdmin A can see default roles and Plumber Lead
        $rolesForA = Role::accessibleFor($superAdminA)->pluck('name')->toArray();
        $this->assertContains('Plumber Lead', $rolesForA);

        // SuperAdmin B can see default roles, but NOT Plumber Lead
        $rolesForB = Role::accessibleFor($superAdminB)->pluck('name')->toArray();
        $this->assertNotContains('Plumber Lead', $rolesForB);
    }

    public function test_driver_role_returns_role_id_6_in_api_me(): void
    {
        // Default roles: member = 1, vehicle_driver = 6
        Role::firstOrCreate(['id' => 1, 'slug' => 'member'], ['name' => 'Member', 'is_default' => 1, 'created_by' => 1]);
        Role::firstOrCreate(['id' => 6, 'slug' => 'vehicle_driver'], ['name' => 'Vehicle Driver', 'is_default' => 1, 'created_by' => 1]);

        $superAdmin = $this->createSuperAdmin('sa@example.com');
        $company = Company::create(['name' => 'Goscopify', 'status' => 'active']);

        // Create an employee member with Driver designation
        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Priyanka Saini',
            'username' => 'priyankasaini',
            'slug' => 'priyanka-saini-' . Str::random(4),
            'email' => 'priynkasaini@example.com',
            'phone' => '9887987543',
            'status' => Member::STATUS_ACTIVE,
            'departments' => ['Operations'],
            'designation' => ['Driver'],
            'roles' => [1], // initial legacy state
            'company_name' => $company->name,
            'created_by' => $superAdmin->id,
            'password' => 'password',
        ]);

        // When queried via accessor or API /api/me:
        $this->assertEquals([6], $member->roles);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/me');
        $response->assertOk();
        $response->assertJsonPath('roles', [6]);
        $response->assertJsonPath('designation_names', 'Driver');
    }

    public function test_employee_role_options_and_syncing_driver_role(): void
    {
        Role::firstOrCreate(['id' => 1, 'slug' => 'member'], ['name' => 'Member', 'is_default' => 1, 'created_by' => 1]);
        Role::firstOrCreate(['id' => 6, 'slug' => 'vehicle_driver'], ['name' => 'Vehicle Driver', 'is_default' => 1, 'created_by' => 1]);

        $superAdmin = $this->createSuperAdmin('sa_opts@example.com');

        // Check getRoleOptions
        $roleOpts = \App\Http\Controllers\SuperAdmin\EmployeeController::getRoleOptions();
        $this->assertEquals([['id' => 1, 'name' => 'Member']], $roleOpts);

        // Check getMemberRoleOptions
        $memberOpts = \App\Http\Controllers\SuperAdmin\EmployeeController::getMemberRoleOptions($superAdmin);
        $driverOpt = collect($memberOpts)->firstWhere('slug', 'vehicle_driver');
        $this->assertNotNull($driverOpt);
        $this->assertEquals(6, $driverOpt['id']);
        $this->assertEquals('Driver', $driverOpt['name']);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Driver Test',
            'username' => 'drivertest',
            'slug' => 'driver-test-' . Str::random(4),
            'email' => 'drivertest@example.com',
            'phone' => '9887987599',
            'status' => Member::STATUS_ACTIVE,
            'departments' => ['Operations'],
            'designation' => ['Driver'],
            'roles' => [1],
            'company_name' => 'Test Company',
            'created_by' => $superAdmin->id,
            'password' => 'password',
        ]);

        $controller = app(\App\Http\Controllers\SuperAdmin\EmployeeController::class);
        $controller->syncMemberRoles($member->id, ['Driver']);

        $this->assertEquals([6], $member->fresh()->roles);
    }
}
