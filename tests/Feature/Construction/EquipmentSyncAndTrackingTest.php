<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use Database\Seeders\Construction\FoundationSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EquipmentSyncAndTrackingTest extends TestCase
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
        static $seq = 300;
        $seq++;

        return SuperAdmin::create([
            'name' => 'Equipment Super Admin',
            'email' => "eqadmin{$seq}@cadmax.com",
            'username' => "eqadmin{$seq}",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 1,
            'is_project_owner' => 1,
        ]);
    }

    private function createCategory(): EquipmentCategory
    {
        return EquipmentCategory::create([
            'category_name' => 'Survey Instruments',
            'status' => 1,
        ]);
    }

    private function createEmployee(SuperAdmin $admin, Company $company): Employee
    {
        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $admin->id,
            'company_id' => $company->id,
            'name' => 'Field Engineer Lokesh',
            'username' => 'lokesh_' . rand(100, 999),
            'slug' => 'lokesh-' . rand(1000, 9999),
            'email' => 'lokesh_' . rand(100, 999) . '@cadmax.com',
            'phone' => '9' . rand(100000000, 999999999),
            'password' => Hash::make('password'),
        ]);

        return Employee::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $member->id,
            'employee_id' => 'EMP-' . rand(100000, 999999),
        ]);
    }

    private function createProject(Company $company): Project
    {
        $client = \App\Models\Client::create([
            'company_id' => $company->id,
            'name' => 'Client Infra',
            'client_code' => 'CLI-INF-' . rand(100, 999),
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Chomu Mhana',
            'slug' => 'chomu-mhana-' . rand(100, 999),
            'project_code' => 'PRJ-00003',
            'status' => 'active',
        ]);
    }

    public function test_super_admin_can_assign_equipment_to_employee_and_it_shows_in_construction_allocations(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);

        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $category = $this->createCategory();
        $employee = $this->createEmployee($admin, $company);
        $project = $this->createProject($company);

        $equipment = Equipment::create([
            'created_by' => $admin->id,
            'company_id' => $company->id,
            'equipment_id' => 'EQ-654925',
            'category_id' => $category->id,
            'equipment_name' => 'TOPOCON',
            'status' => 0,
        ]);

        // 1. Assign to employee and project via EquipmentMasterController::assign
        $assignResponse = $this->actingAs($admin, 'superadmin')
            ->post(route('super.equipment.assign', ['id' => $equipment->id]), [
                'assigned_employee_id' => $employee->id,
                'assigned_project_id' => $project->id,
                'assigned_date' => '2026-10-27',
            ]);

        $assignResponse->assertSessionHasNoErrors();
        $assignResponse->assertRedirect();

        $equipment->refresh();
        $this->assertEquals(1, $equipment->status);
        $this->assertEquals($employee->id, $equipment->assigned_employee_id);
        $this->assertEquals($project->id, $equipment->assigned_project_id);

        // 2. View Construction Equipment tracking page and verify assigned equipment is in allocations
        $trackingResponse = $this->actingAs($admin, 'superadmin')
            ->get(route('super.construction.equipment.index'));

        $trackingResponse->assertOk();
        $trackingResponse->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Construction/Equipment/Index')
            ->has('equipments')
            ->has('allocations')
            ->where('allocations', fn ($allocs) => collect($allocs)->contains(fn ($a) =>
                ($a['equipment']['equipment_code'] ?? null) === 'EQ-654925' ||
                ($a['equipment']['name'] ?? null) === 'TOPOCON'
            ))
        );

        // 3. Return the main equipment allocation via returnAllocation
        $returnResponse = $this->actingAs($admin, 'superadmin')
            ->post(route('super.construction.equipment.allocations.return'), [
                'project_id' => $project->id,
                'allocation_id' => 'main_' . $equipment->id,
            ]);

        $returnResponse->assertSessionHasNoErrors();
        $returnResponse->assertRedirect();

        $equipment->refresh();
        $this->assertEquals(0, $equipment->status);
        $this->assertNull($equipment->assigned_employee_id);
        $this->assertNull($equipment->assigned_project_id);
    }
}
