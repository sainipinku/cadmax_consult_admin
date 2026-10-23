<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLocation;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminLocationScopingTest extends TestCase
{
    private function createSuperAdmin(string $name, string $email, ?int $companyId = null): SuperAdmin
    {
        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'email' => $email,
            'phone' => '9' . random_int(100000000, 999999999),
            'whatsapp_phone' => '9' . random_int(100000000, 999999999),
            'company_id' => $companyId,
            'password' => 'password',
        ]);
    }

    private function createMemberWithLocation(string $name, int $createdBy, ?string $companyName = null, float $lat = 26.9124, float $lng = 75.7873): Member
    {
        $username = Str::slug($name) . '-' . Str::random(5);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $createdBy,
            'company_name' => $companyName,
            'name' => $name,
            'username' => $username,
            'slug' => $username,
            'email' => $username . '@example.com',
            'phone' => '9' . random_int(100000000, 999999999),
            'status' => Member::STATUS_ACTIVE,
            'password' => 'password',
            'latitude' => $lat,
            'longitude' => $lng,
            'location_captured_at' => now(),
        ]);

        Employee::firstOrCreate(
            ['member_id' => $member->id],
            ['uuid' => (string) Str::uuid(), 'employee_id' => 'EMP-' . str_pad((string) random_int(1000, 99999), 6, '0', STR_PAD_LEFT)]
        );

        if (\Illuminate\Support\Facades\Schema::hasTable('employee_locations')) {
            EmployeeLocation::create([
                'member_id' => $member->id,
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy' => 10,
                'captured_at' => now(),
            ]);
        }

        return $member;
    }

    public function test_regular_super_admin_only_sees_their_own_employees_on_map(): void
    {
        // Company A & Super Admin A
        $companyA = Company::create(['name' => 'Org Alpha ' . Str::random(4), 'status' => 'active']);
        $superAdminA = $this->createSuperAdmin('Admin Alpha', 'alpha_' . Str::random(5) . '@test.com', $companyA->id);
        $memberA = $this->createMemberWithLocation('Alpha Member', $superAdminA->id, $companyA->name);

        // Company B & Super Admin B
        $companyB = Company::create(['name' => 'Org Beta ' . Str::random(4), 'status' => 'active']);
        $superAdminB = $this->createSuperAdmin('Admin Beta', 'beta_' . Str::random(5) . '@test.com', $companyB->id);
        $memberB = $this->createMemberWithLocation('Beta Member', $superAdminB->id, $companyB->name);

        // Authenticate as Super Admin A
        $response = $this->actingAs($superAdminA, 'superadmin')
            ->getJson('/api/admin/employee-locations');

        $response->assertStatus(200);
        $data = $response->json('data');

        $employeeNames = collect($data)->pluck('name')->toArray();
        $this->assertContains('Alpha Member', $employeeNames);
        $this->assertNotContains('Beta Member', $employeeNames);

        // Authenticate as Super Admin B
        $responseB = $this->actingAs($superAdminB, 'superadmin')
            ->getJson('/api/admin/employee-locations');

        $responseB->assertStatus(200);
        $dataB = $responseB->json('data');

        $employeeNamesB = collect($dataB)->pluck('name')->toArray();
        $this->assertContains('Beta Member', $employeeNamesB);
        $this->assertNotContains('Alpha Member', $employeeNamesB);
    }

    public function test_project_owner_can_filter_by_super_admin_id(): void
    {
        // Ensure Project Owner exists with id = 1
        $projectOwner = SuperAdmin::find(1);
        if (!$projectOwner) {
            $projectOwner = new SuperAdmin();
            $projectOwner->forceFill([
                'id' => 1,
                'uuid' => (string) Str::uuid(),
                'name' => 'Main Project Owner',
                'email' => 'owner_' . Str::random(5) . '@test.com',
                'phone' => '9999999991',
                'whatsapp_phone' => '9999999991',
                'password' => 'password',
            ])->save();
        }

        $companyC = Company::create(['name' => 'Org Gamma ' . Str::random(4), 'status' => 'active']);
        $superAdminC = $this->createSuperAdmin('Admin Gamma', 'gamma_' . Str::random(5) . '@test.com', $companyC->id);
        $memberC = $this->createMemberWithLocation('Gamma Member', $superAdminC->id, $companyC->name);

        // Project Owner without filter gets employees
        $resAll = $this->actingAs($projectOwner, 'superadmin')
            ->getJson('/api/admin/employee-locations');

        $resAll->assertStatus(200);
        $this->assertTrue($resAll->json('isProjectOwner'));
        $this->assertNotEmpty($resAll->json('superAdmins'));

        // Project Owner filtering specifically for superAdminC
        $resFiltered = $this->actingAs($projectOwner, 'superadmin')
            ->getJson('/api/admin/employee-locations?super_admin_id=' . $superAdminC->id);

        $resFiltered->assertStatus(200);
        $filteredNames = collect($resFiltered->json('data'))->pluck('name')->toArray();
        $this->assertContains('Gamma Member', $filteredNames);
    }

    public function test_regular_super_admin_cannot_view_other_super_admin_employee_location_history(): void
    {
        $companyX = Company::create(['name' => 'Org X ' . Str::random(4), 'status' => 'active']);
        $superAdminX = $this->createSuperAdmin('Admin X', 'x_' . Str::random(5) . '@test.com', $companyX->id);

        $companyY = Company::create(['name' => 'Org Y ' . Str::random(4), 'status' => 'active']);
        $superAdminY = $this->createSuperAdmin('Admin Y', 'y_' . Str::random(5) . '@test.com', $companyY->id);
        $memberY = $this->createMemberWithLocation('Employee Y', $superAdminY->id, $companyY->name);
        $employeeY = $memberY->employee;

        // Admin X tries to view Employee Y's location history
        $response = $this->actingAs($superAdminX, 'superadmin')
            ->getJson("/api/admin/employees/{$employeeY->employee_id}/location-history");

        $response->assertStatus(403);
    }

    public function test_location_tracking_page_renders_with_super_admin_props(): void
    {
        $projectOwner = SuperAdmin::find(1);
        if (!$projectOwner) {
            $projectOwner = new SuperAdmin();
            $projectOwner->forceFill([
                'id' => 1,
                'uuid' => (string) Str::uuid(),
                'name' => 'Main Project Owner',
                'email' => 'owner2_' . Str::random(5) . '@test.com',
                'phone' => '9999999992',
                'whatsapp_phone' => '9999999992',
                'password' => 'password',
            ])->save();
        }

        $response = $this->actingAs($projectOwner, 'superadmin')
            ->get('/super/location-tracking');

        $response->assertStatus(200);
    }
}
