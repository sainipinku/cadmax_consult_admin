<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Client;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EquipmentMasterTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(): Company
    {
        return Company::create([
            'name' => 'Cadmax Construction',
            'status' => 'active',
        ]);
    }

    private function createSuperAdmin(Company $company): SuperAdmin
    {
        static $seq = 1;
        $seq++;

        return SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => "superadmin{$seq}@cadmax.com",
            'username' => "superadmin{$seq}",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 1,
            'is_project_owner' => 0,
        ]);
    }

    private function createProductSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::forceCreate([
            'id' => 1,
            'name' => 'Product Super Admin',
            'email' => 'product-superadmin@cadmax.com',
            'username' => 'product-superadmin',
            'password' => Hash::make('password'),
            'company_id' => null,
            'status' => 1,
        ]);
    }

    private function createCategory(): EquipmentCategory
    {
        return EquipmentCategory::create([
            'category_name' => 'Survey Equipment',
            'status' => 1,
        ]);
    }

    private function createEmployee(SuperAdmin $admin, string $name = 'John Doe'): Employee
    {
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(5));
        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $admin->id,
            'name' => $name,
            'username' => $slug,
            'email' => $slug . '@cadmax.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('password'),
            'slug' => $slug,
        ]);

        return Employee::create([
            'member_id' => $member->id,
        ]);
    }

    private function createProject(Company $company, string $code = 'PRJ-501'): Project
    {
        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Client Construction',
            'client_code' => 'CLI-' . rand(100, 999),
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Highway Overpass',
            'slug' => 'highway-overpass-' . rand(100, 999),
            'project_code' => $code,
            'status' => 'active',
        ]);
    }

    public function test_super_admin_can_view_equipment_list_with_eager_loaded_assignment_details(): void
    {
        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $category = $this->createCategory();
        $employee = $this->createEmployee($admin, 'Robert Technician');
        $project = $this->createProject($company, 'PRJ-999');

        $equipment = Equipment::create([
            'created_by' => $admin->id,
            'company_id' => $company->id,
            'category_id' => $category->id,
            'equipment_name' => 'Total Station TS16',
            'status' => 1,
            'assigned_employee_id' => $employee->id,
            'assigned_project_id' => $project->id,
            'assigned_date' => '2026-10-26',
        ]);

        $response = $this->actingAs($admin, 'superadmin')->get(route('super.equipment.list'));
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Equipment/List')
            ->where('isProductSuperAdmin', false)
            ->where('canManageEquipment', true)
            ->has('equipments.data', 1)
            ->where('equipments.data.0.equipment_name', 'Total Station TS16')
            ->where('equipments.data.0.status', 1)
            ->where('equipments.data.0.assigned_employee.id', $employee->id)
            ->where('equipments.data.0.assigned_employee.member.name', 'Robert Technician')
            ->where('equipments.data.0.assigned_project.id', $project->id)
            ->where('equipments.data.0.assigned_project.project_code', 'PRJ-999')
        );
    }

    public function test_master_equipment_assignment_is_visible_in_equipment_tracking(): void
    {
        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $employee = $this->createEmployee($admin, 'Tracking Employee');
        $project = $this->createProject($company, 'PRJ-TRACK');
        $equipment = Equipment::create([
            'created_by' => $admin->id,
            'company_id' => $company->id,
            'category_id' => $this->createCategory()->id,
            'equipment_name' => 'Assigned Total Station',
            'status' => 1,
            'assigned_employee_id' => $employee->id,
            'assigned_project_id' => $project->id,
            'assigned_date' => '2026-10-27',
        ]);

        $response = $this->actingAs($admin, 'superadmin')
            ->get(route('super.construction.equipment.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Construction/Equipment/Index')
            ->has('allocations', 1)
            ->where('allocations.0.legacy_assignment', true)
            ->where('allocations.0.status', 'active')
            ->where('allocations.0.equipment.equipment_code', $equipment->equipment_id)
            ->where('allocations.0.assigned_member.name', 'Tracking Employee')
            ->where('allocations.0.project.name', 'Highway Overpass')
        );
    }

    public function test_product_super_admin_can_see_company_equipment_assignment_in_tracking(): void
    {
        $productAdmin = $this->createProductSuperAdmin();
        $company = $this->createCompany();
        $companyAdmin = $this->createSuperAdmin($company);
        $employee = $this->createEmployee($companyAdmin, 'Company Tracking Employee');
        $project = $this->createProject($company, 'PRJ-PRODUCT-TRACK');
        $equipment = Equipment::create([
            'created_by' => $companyAdmin->id,
            'company_id' => $company->id,
            'category_id' => $this->createCategory()->id,
            'equipment_name' => 'Company Assigned Total Station',
            'status' => 1,
            'assigned_employee_id' => $employee->id,
            'assigned_project_id' => $project->id,
            'assigned_date' => '2026-10-27',
        ]);

        $response = $this->actingAs($productAdmin, 'superadmin')
            ->get(route('super.construction.equipment.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Construction/Equipment/Index')
            ->has('equipments', 1)
            ->where('equipments.0.equipment_code', $equipment->equipment_id)
            ->has('allocations', 1)
            ->where('allocations.0.legacy_assignment', true)
            ->where('allocations.0.equipment.equipment_code', $equipment->equipment_id)
            ->where('allocations.0.assigned_member.name', 'Company Tracking Employee')
        );
    }

    public function test_storing_equipment_with_assignment_details_automatically_promotes_status_to_assigned(): void
    {
        $company = $this->createCompany();
        $otherCompany = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $category = $this->createCategory();
        $employee = $this->createEmployee($admin, 'Sarah Engineer');
        $project = $this->createProject($company, 'PRJ-102');

        // Form submitted with status=0 (Available) but with an assigned employee & project
        $response = $this->actingAs($admin, 'superadmin')->post(route('super.equipment.store'), [
            'company_id' => $otherCompany->id,
            'category_id' => $category->id,
            'equipment_name' => 'Auto Level Nikon',
            'quantity' => 4,
            'ownership_type' => 'rented',
            'location_site' => 'Central Depot',
            'notes' => 'Inspect before each use.',
            'status' => 0,
            'assigned_employee_id' => $employee->id,
            'assigned_project_id' => $project->id,
            'assigned_date' => '2026-10-26',
        ]);

        $response->assertRedirect();

        $equipment = Equipment::where('equipment_name', 'Auto Level Nikon')->first();
        $this->assertNotNull($equipment);
        // Status should be automatically promoted to Assigned (1)
        $this->assertEquals(1, $equipment->status);
        $this->assertEquals($employee->id, $equipment->assigned_employee_id);
        $this->assertEquals($project->id, $equipment->assigned_project_id);
        $this->assertEquals('2026-10-26', $equipment->assigned_date?->format('Y-m-d'));
        $this->assertEquals($company->id, $equipment->company_id);
        $this->assertEquals(4, $equipment->quantity);
        $this->assertEquals('rented', $equipment->ownership_type);
        $this->assertEquals('Central Depot', $equipment->location_site);
        $this->assertEquals('Inspect before each use.', $equipment->notes);
    }

    public function test_product_super_admin_can_only_view_equipment_listing(): void
    {
        $productAdmin = $this->createProductSuperAdmin();
        $company = $this->createCompany();
        $category = $this->createCategory();
        $equipment = Equipment::create([
            'created_by' => $productAdmin->id,
            'company_id' => $company->id,
            'category_id' => $category->id,
            'equipment_name' => 'Product Admin Equipment',
            'quantity' => 3,
            'ownership_type' => 'leased',
            'location_site' => 'North Yard',
            'notes' => 'Quarterly inspection due.',
            'status' => 0,
        ]);

        $this->actingAs($productAdmin, 'superadmin')
            ->get(route('super.equipment.list'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('isProductSuperAdmin', true)
                ->where('canManageEquipment', false)
                ->has('equipments.data', 1)
                ->where('equipments.data.0.id', $equipment->id)
                ->where('equipments.data.0.owning_company.name', $company->name)
                ->where('equipments.data.0.quantity', 3)
                ->where('equipments.data.0.ownership_type', 'leased')
                ->where('equipments.data.0.location_site', 'North Yard')
            );

        $this->actingAs($productAdmin, 'superadmin')
            ->post(route('super.equipment.store'), [
                'category_id' => $category->id,
                'equipment_name' => 'Product Admin Equipment',
                'quantity' => 1,
                'ownership_type' => 'owned',
                'status' => 0,
            ])
            ->assertForbidden();
    }

    public function test_company_super_admin_equipment_listing_is_scoped_to_its_company(): void
    {
        $company = $this->createCompany();
        $otherCompany = Company::create(['name' => 'Other Company', 'status' => 'active']);
        $admin = $this->createSuperAdmin($company);
        $category = $this->createCategory();

        Equipment::create([
            'created_by' => $admin->id,
            'company_id' => $company->id,
            'category_id' => $category->id,
            'equipment_name' => 'Company Equipment',
            'status' => 0,
        ]);
        Equipment::create([
            'created_by' => 999,
            'company_id' => $otherCompany->id,
            'category_id' => $category->id,
            'equipment_name' => 'Other Company Equipment',
            'status' => 0,
        ]);

        $this->actingAs($admin, 'superadmin')
            ->get(route('super.equipment.list'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('equipments.data', 1)
                ->where('equipments.data.0.equipment_name', 'Company Equipment')
            );
    }

    public function test_storing_equipment_with_status_assigned_defaults_assigned_date_to_today_if_omitted(): void
    {
        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $category = $this->createCategory();
        $employee = $this->createEmployee($admin, 'Mark Operator');

        $response = $this->actingAs($admin, 'superadmin')->post(route('super.equipment.store'), [
            'category_id' => $category->id,
            'equipment_name' => 'Theodolite Digital',
            'status' => 1,
            'assigned_employee_id' => $employee->id,
            // assigned_date omitted
        ]);

        $response->assertRedirect();

        $equipment = Equipment::where('equipment_name', 'Theodolite Digital')->first();
        $this->assertNotNull($equipment);
        $this->assertEquals(1, $equipment->status);
        $this->assertEquals(now()->toDateString(), $equipment->assigned_date?->format('Y-m-d'));
    }

    public function test_updating_equipment_can_assign_and_unassign(): void
    {
        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $category = $this->createCategory();
        $employee = $this->createEmployee($admin, 'Alex Surveyor');
        $project = $this->createProject($company, 'PRJ-303');

        $equipment = Equipment::create([
            'created_by' => $admin->id,
            'company_id' => $company->id,
            'category_id' => $category->id,
            'equipment_name' => 'Laser Measure GLM 50',
            'status' => 0,
        ]);

        // Assign to employee
        $response = $this->actingAs($admin, 'superadmin')->put(route('super.equipment.update', $equipment->id), [
            'category_id' => $category->id,
            'equipment_name' => 'Laser Measure GLM 50',
            'status' => 0, // Left at 0 in form, but employee provided
            'assigned_employee_id' => $employee->id,
            'assigned_project_id' => $project->id,
            'assigned_date' => '2026-10-26',
        ]);

        $response->assertRedirect();
        $equipment->refresh();
        $this->assertEquals(1, $equipment->status);
        $this->assertEquals($employee->id, $equipment->assigned_employee_id);
        $this->assertEquals($project->id, $equipment->assigned_project_id);

        // Now unassign by explicitly changing status to 0 (Available) without assignments
        $response = $this->actingAs($admin, 'superadmin')->put(route('super.equipment.update', $equipment->id), [
            'category_id' => $category->id,
            'equipment_name' => 'Laser Measure GLM 50',
            'status' => 0,
            'assigned_employee_id' => null,
            'assigned_project_id' => null,
            'assigned_date' => null,
        ]);

        $response->assertRedirect();
        $equipment->refresh();
        $this->assertEquals(0, $equipment->status);
        $this->assertNull($equipment->assigned_employee_id);
        $this->assertNull($equipment->assigned_project_id);
        $this->assertNull($equipment->assigned_date);
    }

    public function test_show_equipment_returns_assigned_details_json(): void
    {
        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $category = $this->createCategory();
        $employee = $this->createEmployee($admin, 'David Tech');
        $project = $this->createProject($company, 'PRJ-707');

        $equipment = Equipment::create([
            'created_by' => $admin->id,
            'company_id' => $company->id,
            'category_id' => $category->id,
            'equipment_name' => 'Dumpy Level Pro',
            'status' => 1,
            'assigned_employee_id' => $employee->id,
            'assigned_project_id' => $project->id,
            'assigned_date' => '2026-10-26',
        ]);

        $response = $this->actingAs($admin, 'superadmin')->get(route('super.equipment.show', $equipment->id));
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'equipment' => [
                'id' => $equipment->id,
                'equipment_name' => 'Dumpy Level Pro',
                'assigned_employee' => [
                    'id' => $employee->id,
                    'member' => [
                        'name' => 'David Tech',
                    ],
                ],
                'assigned_project' => [
                    'id' => $project->id,
                    'project_code' => 'PRJ-707',
                ],
            ],
        ]);
    }

    public function test_super_admin_can_assign_equipment_directly_via_assign_endpoint(): void
    {
        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $category = $this->createCategory();
        $employee = $this->createEmployee($admin, 'Jessica Miller');
        $project = $this->createProject($company, 'PRJ-404');

        $equipment = Equipment::create([
            'created_by' => $admin->id,
            'company_id' => $company->id,
            'category_id' => $category->id,
            'equipment_name' => 'Plane Table',
            'status' => 0,
        ]);

        $this->assertEquals(0, $equipment->status);
        $this->assertNull($equipment->assigned_employee_id);

        $response = $this->actingAs($admin, 'superadmin')->post(route('super.equipment.assign', $equipment->id), [
            'assigned_employee_id' => $employee->id,
            'assigned_project_id' => $project->id,
            'assigned_date' => '2026-10-26',
        ]);

        $response->assertRedirect();
        $equipment->refresh();

        $this->assertEquals(1, $equipment->status);
        $this->assertEquals($employee->id, $equipment->assigned_employee_id);
        $this->assertEquals($project->id, $equipment->assigned_project_id);
        $this->assertEquals('2026-10-26', $equipment->assigned_date?->format('Y-m-d'));
    }

    public function test_super_admin_can_unassign_equipment_via_unassign_endpoint(): void
    {
        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $category = $this->createCategory();
        $employee = $this->createEmployee($admin, 'Jessica Miller');
        $project = $this->createProject($company, 'PRJ-404');

        $equipment = Equipment::create([
            'created_by' => $admin->id,
            'company_id' => $company->id,
            'category_id' => $category->id,
            'equipment_name' => 'Plane Table',
            'status' => 1,
            'assigned_employee_id' => $employee->id,
            'assigned_project_id' => $project->id,
            'assigned_date' => '2026-10-26',
        ]);

        $response = $this->actingAs($admin, 'superadmin')->post(route('super.equipment.unassign', $equipment->id));

        $response->assertRedirect();
        $equipment->refresh();

        $this->assertEquals(0, $equipment->status);
        $this->assertNull($equipment->assigned_employee_id);
        $this->assertNull($equipment->assigned_project_id);
        $this->assertNull($equipment->assigned_date);
    }
}
