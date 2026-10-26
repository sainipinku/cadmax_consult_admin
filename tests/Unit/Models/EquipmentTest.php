<?php

namespace Tests\Unit\Models;

use App\Models\Client;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EquipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_casts_dates_as_ymd_string(): void
    {
        $equipment = new Equipment();
        $equipment->purchase_date = '2026-10-26';
        $equipment->warranty_till = '2027-10-26';
        $equipment->assigned_date = '2026-10-26';

        $array = $equipment->toArray();

        $this->assertEquals('2026-10-26', $array['purchase_date']);
        $this->assertEquals('2027-10-26', $array['warranty_till']);
        $this->assertEquals('2026-10-26', $array['assigned_date']);
    }

    public function test_equipment_relationships(): void
    {
        $company = Company::create(['name' => 'Test Co', 'status' => 'active']);
        $admin = SuperAdmin::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'username' => 'admin_test',
            'password' => 'secret',
            'company_id' => $company->id,
            'status' => 1,
            'is_project_owner' => 0,
        ]);

        $category = EquipmentCategory::create([
            'category_name' => 'Heavy Machinery',
            'status' => 1,
        ]);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $admin->id,
            'name' => 'Alice Operator',
            'username' => 'alice_op',
            'email' => 'alice@test.com',
            'phone' => '9876543210',
            'password' => 'password',
            'slug' => 'alice-operator',
        ]);

        $employee = Employee::create([
            'member_id' => $member->id,
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Client One',
            'client_code' => 'CLI-100',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Metro Site',
            'slug' => 'metro-site',
            'project_code' => 'PRJ-100',
            'status' => 'active',
        ]);

        $equipment = Equipment::create([
            'category_id' => $category->id,
            'equipment_name' => 'Excavator 3000',
            'status' => 1,
            'assigned_employee_id' => $employee->id,
            'assigned_project_id' => $project->id,
            'assigned_date' => '2026-10-26',
        ]);

        $this->assertInstanceOf(EquipmentCategory::class, $equipment->category);
        $this->assertEquals('Heavy Machinery', $equipment->category->category_name);

        $this->assertInstanceOf(Employee::class, $equipment->assignedEmployee);
        $this->assertEquals($employee->id, $equipment->assignedEmployee->id);
        $this->assertEquals('Alice Operator', $equipment->assignedEmployee->member->name);

        $this->assertInstanceOf(Project::class, $equipment->assignedProject);
        $this->assertEquals('PRJ-100', $equipment->assignedProject->project_code);
    }
}
