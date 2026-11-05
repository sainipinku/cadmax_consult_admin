<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Member;
use App\Models\Project;
use App\Models\Company;
use App\Models\ConstructionVehicle;
use App\Models\VehicleAssignment;
use App\Models\ConstructionEquipment;
use App\Models\EquipmentAllocation;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MemberVehicleEquipmentHistoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_vehicle_assignment_history_summary_calculation(): void
    {
        $company = Company::create(['name' => 'Unit Infra Company', 'status' => 'active']);
        $project = Project::create([
            'company_id' => $company->id,
            'name' => 'Unit Project',
            'status' => 'active',
            'slug' => 'unit-proj-' . uniqid(),
            'project_code' => 'UPRJ-' . rand(100, 999),
        ]);

        $member = Member::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Driver One',
            'username' => 'driver_one_' . uniqid(),
            'email' => 'driver_one_' . uniqid() . '@example.com',
            'phone' => '98765' . rand(10000, 99999),
            'password' => bcrypt('password123'),
            'status' => 1,
            'created_by' => 1,
            'slug' => 'driver-one-' . uniqid(),
        ]);

        $vehicle = ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => 'VHC-UNIT-01',
            'registration_number' => 'DL-9C-1122',
            'make' => 'Mahindra',
            'model' => 'Bolero',
            'status' => 'active',
        ]);

        VehicleAssignment::create([
            'project_id' => $project->id,
            'vehicle_id' => $vehicle->id,
            'driver_member_id' => $member->id,
            'assigned_from' => now()->subDays(3),
            'assigned_to' => now()->addDays(2),
            'status' => 'active',
        ]);

        $assignments = VehicleAssignment::where('driver_member_id', $member->id)->get();

        $this->assertCount(1, $assignments);
        $this->assertEquals('active', $assignments->first()->status);
        $this->assertEquals($vehicle->id, $assignments->first()->vehicle_id);
    }

    public function test_equipment_allocation_history_summary_calculation(): void
    {
        $company = Company::create(['name' => 'Unit Infra Company 2', 'status' => 'active']);
        $project = Project::create([
            'company_id' => $company->id,
            'name' => 'Unit Project 2',
            'status' => 'active',
            'slug' => 'unit-proj2-' . uniqid(),
            'project_code' => 'UPRJ2-' . rand(100, 999),
        ]);

        $member = Member::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Operator One',
            'username' => 'operator_one_' . uniqid(),
            'email' => 'operator_one_' . uniqid() . '@example.com',
            'phone' => '98765' . rand(10000, 99999),
            'password' => bcrypt('password123'),
            'status' => 1,
            'created_by' => 1,
            'slug' => 'operator-one-' . uniqid(),
        ]);

        $equipment = ConstructionEquipment::create([
            'project_id' => $project->id,
            'equipment_code' => 'EQP-UNIT-01',
            'name' => 'Excavator Unit',
            'status' => 'active',
        ]);

        EquipmentAllocation::create([
            'project_id' => $project->id,
            'equipment_id' => $equipment->id,
            'assigned_to_member_id' => $member->id,
            'assigned_from' => now()->subDays(5),
            'assigned_to' => now()->subDay(),
            'returned_at' => now()->subDay(),
            'hours' => 18.5,
            'status' => 'returned',
        ]);

        $allocations = EquipmentAllocation::where('assigned_to_member_id', $member->id)->get();

        $this->assertCount(1, $allocations);
        $this->assertEquals(18.5, $allocations->first()->hours);
        $this->assertNotNull($allocations->first()->returned_at);
    }
}

