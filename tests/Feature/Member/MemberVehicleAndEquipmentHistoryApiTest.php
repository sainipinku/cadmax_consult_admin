<?php

namespace Tests\Feature\Member;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionEquipment;
use App\Models\ConstructionVehicle;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentCategory;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Models\VehicleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberVehicleAndEquipmentHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private SuperAdmin $admin;
    private Project $project;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'CadMax Infrastructure',
            'status' => 'active',
        ]);

        $this->admin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@cadmax.com',
            'password' => 'password',
            'company_id' => $this->company->id,
            'roles' => json_encode(['super_admin']),
            'status' => 1,
        ]);

        $client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Highway Authority',
            'client_code' => 'CLI-HWY',
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'name' => 'Expressway Segment 4',
            'project_code' => 'PRJ-EXP-04',
            'slug' => 'expressway-segment-4',
            'status' => 'active',
        ]);

        $this->member = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Vikram Driver',
            'email' => 'vikram@example.com',
            'username' => 'vikram_driver',
            'slug' => 'vikram-driver',
            'phone' => '9876543210',
            'password' => bcrypt('password123'),
            'company_name' => 'CadMax Infrastructure',
            'created_by' => $this->admin->id,
            'status' => 1,
            'roles' => ['driver'],
        ]);
    }

    public function test_unauthenticated_request_is_rejected_for_vehicle_history(): void
    {
        $response = $this->getJson('/api/member/vehicles/history');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_request_is_rejected_for_equipment_history(): void
    {
        $response = $this->getJson('/api/member/equipment/history');
        $response->assertStatus(401);
    }

    public function test_authenticated_member_gets_vehicle_history_list(): void
    {
        $vehicle1 = ConstructionVehicle::create([
            'project_id' => $this->project->id,
            'vehicle_code' => 'VHC-000001',
            'registration_number' => 'RJ14DE3551',
            'make' => 'Tata',
            'model' => '407 LPT',
            'vehicle_type' => 'Light Commercial',
            'status' => 'active',
        ]);

        $vehicle2 = ConstructionVehicle::create([
            'project_id' => $this->project->id,
            'vehicle_code' => 'VHC-000002',
            'registration_number' => 'RJ14XY9988',
            'make' => 'Mahindra',
            'model' => 'Bolero Camper',
            'vehicle_type' => 'Utility',
            'status' => 'active',
        ]);

        // Active assignment
        VehicleAssignment::create([
            'project_id' => $this->project->id,
            'vehicle_id' => $vehicle1->id,
            'driver_member_id' => $this->member->id,
            'assigned_from' => now()->subDays(2),
            'assigned_to' => now()->addDays(5),
            'status' => 'active',
        ]);

        // Completed assignment in past
        VehicleAssignment::create([
            'project_id' => $this->project->id,
            'vehicle_id' => $vehicle2->id,
            'driver_member_id' => $this->member->id,
            'assigned_from' => now()->subDays(15),
            'assigned_to' => now()->subDays(10),
            'status' => 'completed',
        ]);

        // Another member's assignment (should NOT be returned)
        $otherMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Person',
            'email' => 'other@example.com',
            'username' => 'other_user',
            'slug' => 'other-user',
            'phone' => '9998887771',
            'password' => bcrypt('password123'),
            'created_by' => $this->admin->id,
            'status' => 1,
        ]);
        VehicleAssignment::create([
            'project_id' => $this->project->id,
            'vehicle_id' => $vehicle1->id,
            'driver_member_id' => $otherMember->id,
            'assigned_from' => now()->subDays(30),
            'assigned_to' => now()->subDays(25),
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/vehicles/history');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'member' => [
                    'id' => $this->member->id,
                    'name' => 'Vikram Driver',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'member' => ['id', 'name', 'email'],
                'summary' => [
                    'total_assignments',
                    'active_assignments',
                    'completed_assignments',
                    'unique_vehicles_used',
                ],
                'data' => [
                    '*' => [
                        'assignment_id',
                        'vehicle_id',
                        'vehicle_code',
                        'registration_number',
                        'vehicle_name',
                        'make',
                        'model',
                        'status',
                        'assigned_from',
                        'assigned_to',
                        'project' => ['id', 'name', 'project_code'],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals(2, $response->json('summary.total_assignments'));
        $this->assertEquals(1, $response->json('summary.active_assignments'));
        $this->assertEquals(1, $response->json('summary.completed_assignments'));
        $this->assertEquals(2, $response->json('summary.unique_vehicles_used'));

        // Also test alias route
        $aliasResponse = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/vehicle-history');
        $aliasResponse->assertStatus(200);
        $this->assertCount(2, $aliasResponse->json('data'));
    }

    public function test_vehicle_history_supports_filtering_by_status_and_search(): void
    {
        $vehicle = ConstructionVehicle::create([
            'project_id' => $this->project->id,
            'vehicle_code' => 'VHC-000099',
            'registration_number' => 'DL01AB1234',
            'make' => 'Ashok Leyland',
            'model' => 'Dost+',
            'status' => 'active',
        ]);

        VehicleAssignment::create([
            'project_id' => $this->project->id,
            'vehicle_id' => $vehicle->id,
            'driver_member_id' => $this->member->id,
            'assigned_from' => now()->subDay(),
            'assigned_to' => now()->addDays(2),
            'status' => 'active',
        ]);

        // Filter by active status
        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/vehicles/history?status=active');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        // Filter by completed status (none should match)
        $completedResponse = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/vehicles/history?status=completed');
        $completedResponse->assertStatus(200);
        $this->assertCount(0, $completedResponse->json('data'));

        // Search by registration number
        $searchResponse = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/vehicles/history?search=DL01AB1234');
        $searchResponse->assertStatus(200);
        $this->assertCount(1, $searchResponse->json('data'));
    }

    public function test_authenticated_member_gets_equipment_history_list(): void
    {
        $constructionEquipment = ConstructionEquipment::create([
            'project_id' => $this->project->id,
            'equipment_code' => 'EQP-000001',
            'name' => 'Hydraulic Excavator CAT 320D',
            'equipment_type' => 'Heavy Excavator',
            'serial_number' => 'CAT320D-99812',
            'status' => 'active',
        ]);

        // Construction equipment allocation
        EquipmentAllocation::create([
            'project_id' => $this->project->id,
            'equipment_id' => $constructionEquipment->id,
            'assigned_to_member_id' => $this->member->id,
            'allocated_at' => now()->subDays(5),
            'assigned_from' => now()->subDays(5),
            'assigned_to' => now()->addDays(2),
            'hours' => 24.5,
            'status' => 'active',
            'notes' => 'Pavement trench digging',
        ]);

        // Main equipment record assigned to this member via Employee record
        $category = EquipmentCategory::create([
            'category_name' => 'Survey Instruments',
            'category_id' => 'EC-' . rand(100000, 999999),
        ]);

        $employee = \App\Models\Employee::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $this->member->id,
            'employee_id' => 'EMP-001',
        ]);

        Equipment::create([
            'equipment_name' => 'Leica Total Station TS16',
            'category_id' => $category->id,
            'assigned_employee_id' => $employee->id,
            'assigned_project_id' => $this->project->id,
            'serial_number' => 'LEICA-TS16-4411',
            'brand' => 'Leica',
            'model' => 'TS16',
            'status' => 1,
            'assigned_date' => now()->subDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/equipment/history');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'member' => [
                    'id' => $this->member->id,
                    'name' => 'Vikram Driver',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'member' => ['id', 'name', 'email'],
                'summary' => [
                    'total_allocations',
                    'active_allocations',
                    'returned_allocations',
                    'unique_equipments_used',
                ],
                'data' => [
                    '*' => [
                        'equipment_id',
                        'equipment_name',
                        'status',
                        'project' => ['id', 'name'],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals(2, $response->json('summary.total_allocations'));
        $this->assertEquals(2, $response->json('summary.active_allocations'));

        // Test alias route
        $aliasResponse = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/member/equipment-history');
        $aliasResponse->assertStatus(200);
        $this->assertCount(2, $aliasResponse->json('data'));
    }
}
