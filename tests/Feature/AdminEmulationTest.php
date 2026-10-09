<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminEmulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_emulate_company_admin_and_access_dashboard(): void
    {
        $company = Company::create(['name' => 'Test Company', 'status' => 'active']);

        $superAdmin = SuperAdmin::create([
            'name' => 'Company Super Admin',
            'email' => 'superadmin@company.com',
            'username' => 'superadmin_company',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 1,
        ]);

        $adminMember = Member::create([
            'name' => 'Company Admin User',
            'username' => 'admin_user',
            'email' => 'admin@company.com',
            'password' => Hash::make('password'),
            'created_by' => $superAdmin->id,
            'slug' => 'admin',
            'status' => 1,
            'roles' => [1],
        ]);

        // Start emulation
        $response = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('emulate.start'), [
                'target_type' => 'admin',
                'target_id' => $adminMember->id,
            ]);

        $response->assertRedirect(route('admin.dashboard'));

        // Follow redirect to admin dashboard as emulated user
        $dashboardResponse = $this->get(route('admin.construction.dashboard'));
        $dashboardResponse->assertOk();
    }

    public function test_duplicate_phone_number_returns_validation_error_instead_of_500_sql_exception(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Owner Admin',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        Member::create([
            'name' => 'Existing Member',
            'email' => 'existing@example.com',
            'username' => 'existing_user',
            'phone' => '08876465646',
            'password' => Hash::make('password'),
            'created_by' => $superAdmin->id,
            'slug' => 'existing-user',
            'status' => 1,
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.super_admins.store_admin'), [
                'name' => 'New Admin',
                'email' => 'newadmin@example.com',
                'phone' => '08876465646', // Same duplicate phone number
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_slug' => 'admin',
            ]);

        $response->assertSessionHasErrors(['phone']);
    }
}
