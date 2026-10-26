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
            ->has('equipments.data', 1)
            ->where('equipments.data.0.equipment_name', 'Total Station TS16')
            ->where('equipments.data.0.status', 1)
            ->where('equipments.data.0.assigned_employee.id', $employee->id)
            ->where('equipments.data.0.assigned_employee.member.name', 'Robert Technician')
            ->where('equipments.data.0.assigned_project.id', $project->id)
            ->where('equipments.data.0.assigned_project.project_code', 'PRJ-999')
        );
    }

    public function test_storing_equipment_with_assignment_details_automatically_promotes_status_to_assigned(): void
    {
        $company = $this->createCompany();
        $admin = $this->createSuperAdmin($company);
        $category = $this->createCategory();
        $employee = $this->createEmployee($admin, 'Sarah Engineer');
        $project = $this->createProject($company, 'PRJ-102');

        // Form submitted with status=0 (Available) but with an assigned employee & project
        $response = $this->actingAs($admin, 'superadmin')->post(route('super.equipment.store'), [
            'category_id' => $category->id,
            'equipment_name' => 'Auto Level Nikon',
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
}
