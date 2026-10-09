<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminCompanyCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_and_company_are_created_together_in_one_flow(): void
    {
        $currentSuperAdmin = SuperAdmin::create([
            'name' => 'Root Super Admin',
            'email' => 'root@example.com',
            'username' => 'root_admin',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        $response = $this->actingAs($currentSuperAdmin, 'superadmin')
            ->post(route('super.super_admins.store_superadmin'), [
                'name' => 'New Super Admin',
                'email' => 'newsuperadmin@example.com',
                'username' => 'new_superadmin',
                'phone' => '9876543210',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                
                // Merged company flow inputs
                'company_name' => 'Apex Infra Solutions',
                'company_legal_name' => 'Apex Infra Private Limited',
                'company_email' => 'contact@apexinfra.com',
                'company_phone' => '0112345678',
                'company_gst_number' => '07AAAAA0000A1Z5',
                'company_address' => 'New Delhi, India',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // 1. Verify SuperAdmin created
        $createdSuperAdmin = SuperAdmin::where('email', 'newsuperadmin@example.com')->first();
        $this->assertNotNull($createdSuperAdmin);
        $this->assertEquals('New Super Admin', $createdSuperAdmin->name);
        $this->assertNotNull($createdSuperAdmin->company_id);

        // 2. Verify Company created
        $createdCompany = Company::find($createdSuperAdmin->company_id);
        $this->assertNotNull($createdCompany);
        $this->assertEquals('Apex Infra Solutions', $createdCompany->name);
        $this->assertEquals('Apex Infra Private Limited', $createdCompany->legal_name);
        $this->assertEquals(SuperAdmin::class, $createdCompany->created_by_type);
        $this->assertEquals($createdSuperAdmin->id, $createdCompany->created_by_id);

        // 3. Verify relationships
        $this->assertEquals($createdCompany->id, $createdSuperAdmin->company->id);
        $this->assertEquals($createdSuperAdmin->id, $createdCompany->superAdmin->id);
    }

    public function test_non_project_owner_cannot_create_super_admin(): void
    {
        $projectOwner = SuperAdmin::create([
            'id' => 1,
            'name' => 'Project Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        $nonOwner = SuperAdmin::create([
            'id' => 2,
            'name' => 'Secondary Super Admin',
            'email' => 'secondary@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        $response = $this->actingAs($nonOwner, 'superadmin')
            ->post(route('super.super_admins.store_superadmin'), [
                'name' => 'Blocked Super Admin',
                'email' => 'blocked@example.com',
                'company_name' => 'Unauthorized Corp',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('super_admins', ['email' => 'blocked@example.com']);
    }

    public function test_multiple_admin_accounts_can_be_created_without_slug_collision(): void
    {
        $superAdmin = SuperAdmin::create([
            'id' => 1,
            'name' => 'Project Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.super_admins.store_admin'), [
                'name' => 'First Admin',
                'email' => 'admin1@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_slug' => 'admin',
            ])->assertSessionHasNoErrors();

        $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.super_admins.store_admin'), [
                'name' => 'Second Admin',
                'email' => 'admin2@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_slug' => 'admin',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('members', ['email' => 'admin1@example.com']);
        $this->assertDatabaseHas('members', ['email' => 'admin2@example.com']);
    }

    public function test_non_owner_super_admin_and_emulated_members_only_see_their_own_company_projects(): void
    {
        $company1 = Company::create(['name' => 'NextGen', 'status' => 'active']);
        $company2 = Company::create(['name' => 'Gentech', 'status' => 'active']);

        $nextGenSuperAdmin = SuperAdmin::create([
            'id' => 10,
            'name' => 'NextGen SuperAdmin',
            'email' => 'nextgen@example.com',
            'password' => Hash::make('password'),
            'company_id' => $company1->id,
            'status' => 1,
        ]);

        $gentechSuperAdmin = SuperAdmin::create([
            'id' => 11,
            'name' => 'Gentech SuperAdmin',
            'email' => 'gentech@example.com',
            'password' => Hash::make('password'),
            'company_id' => $company2->id,
            'status' => 1,
        ]);

        $client1 = \App\Models\Client::create([
            'company_id' => $company1->id,
            'name' => 'NextGen Client',
            'client_code' => 'CLI-NEXT-01',
            'status' => 'active',
        ]);

        $client2 = \App\Models\Client::create([
            'company_id' => $company2->id,
            'name' => 'Gentech Client',
            'client_code' => 'CLI-GEN-01',
            'status' => 'active',
        ]);

        $nextGenProject = \App\Models\Project::create([
            'company_id' => $company1->id,
            'client_id' => $client1->id,
            'name' => 'NextGen Metro Project',
            'slug' => 'nextgen-metro-project',
            'project_code' => 'PRJ-NEXT-01',
            'status' => 'active',
        ]);

        $gentechProject = \App\Models\Project::create([
            'company_id' => $company2->id,
            'client_id' => $client2->id,
            'name' => 'Gentech Metro Project',
            'slug' => 'gentech-metro-project',
            'project_code' => 'PRJ-GEN-01',
            'status' => 'active',
        ]);

        $nextGenEmployee = \App\Models\Member::create([
            'name' => 'Ram Sharama',
            'username' => 'ram_sharama',
            'email' => 'ram@nextgen.com',
            'password' => Hash::make('password'),
            'created_by' => $nextGenSuperAdmin->id,
            'slug' => 'ram-sharama',
            'status' => 1,
        ]);

        // 1. NextGen SuperAdmin should only see NextGen Project
        $response1 = $this->actingAs($nextGenSuperAdmin, 'superadmin')
            ->get(route('super.construction.projects.index'));
        $response1->assertOk();
        $response1->assertInertia(fn ($page) => $page
            ->where('projects.0.id', $nextGenProject->id)
            ->has('projects', 1)
        );

        // 3. NextGen SuperAdmin should only see NextGen Client and Company
        $response2 = $this->actingAs($nextGenSuperAdmin, 'superadmin')
            ->get(route('super.construction.clients.index'));
        $response2->assertOk();
        $response2->assertInertia(fn ($page) => $page
            ->where('clients.0.id', $client1->id)
            ->has('clients', 1)
            ->where('companies.0.id', $company1->id)
            ->has('companies', 1)
        );
    }
}
