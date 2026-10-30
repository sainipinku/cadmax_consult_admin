<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionEquipment;
use App\Models\ConstructionRole;
use App\Models\ConstructionVehicle;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\Role;
use App\Models\SuperAdmin;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MultiCompanyFleetAndAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(string $name): Company
    {
        return Company::create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function createCrmSuperAdmin(?Company $company = null): SuperAdmin
    {
        $admin = new SuperAdmin([
            'name' => 'CRM Super Admin',
            'email' => 'crm_superadmin@example.com',
            'username' => 'crmsuperadmin',
            'password' => Hash::make('password'),
            'company_id' => $company?->id,
            'status' => 1,
            'roles' => ['superadmin', 'crm_super_admin'],
        ]);
        $admin->id = 1;
        $admin->save();

        return $admin;
    }

    private function createCompanySuperAdmin(Company $company, string $name = 'PCL Admin'): SuperAdmin
    {
        static $seq = 200;
        $seq++;
        return SuperAdmin::create([
            'name' => $name,
            'email' => "pcl_admin_{$seq}@example.com",
            'username' => "pcladmin_{$seq}",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 1,
            'is_project_owner' => 0,
        ]);
    }

    private function createProject(Company $company, string $code, string $name): Project
    {
        $client = Client::create([
            'company_id' => $company->id,
            'name' => "Client {$company->name}",
            'client_code' => "CLI-{$company->id}-" . rand(100, 999),
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . rand(100, 999),
            'project_code' => $code,
            'status' => 'active',
        ]);
    }

    /**
     * Requirement 1:
     * When Company Super Admin adds a vehicle, CRM Super Admin must see it in the vehicle list
     * with company info, while Company Super Admin sees only their own company's vehicles.
     */
    public function test_crm_super_admin_can_see_vehicles_added_by_company_super_admin(): void
    {
        $crmCompany = $this->createCompany('CRM Tech');
        $pclCompany = $this->createCompany('PCL Construction');

        $crmSuperAdmin = $this->createCrmSuperAdmin($crmCompany);
        $pclSuperAdmin = $this->createCompanySuperAdmin($pclCompany, 'PCL Super Admin');

        $pclVehicle = Vehicle::create([
            'vehicle_name' => 'PCL Dumper Truck',
            'vehicle_number' => 'DL01AB1234',
            'vehicle_type' => 'Truck',
            'brand' => 'Tata',
            'fuel_type' => 'Diesel',
            'status' => 0,
            'created_by' => $pclSuperAdmin->id,
            'company_id' => $pclCompany->id,
        ]);

        // CRM Super Admin views vehicle list -> should see PCL's vehicle
        $response = $this->actingAs($crmSuperAdmin, 'superadmin')->get(route('super.vehicles.list'));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Vehicles/List')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.id', $pclVehicle->id)
        );

        // PCL Super Admin views vehicle list -> sees their vehicle
        $pclResponse = $this->actingAs($pclSuperAdmin, 'superadmin')->get(route('super.vehicles.list'));
        $pclResponse->assertOk();
        $pclResponse->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Vehicles/List')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.id', $pclVehicle->id)
        );
    }

    /**
     * Requirement 2:
     * When Company Super Admin assigns a vehicle, CRM Super Admin must see the assigned vehicle
     * in the vehicle assignments list and in the dashboard API.
     */
    public function test_crm_super_admin_can_see_company_vehicle_assignments_in_list_and_dashboard_api(): void
    {
        $crmCompany = $this->createCompany('CRM Tech');
        $pclCompany = $this->createCompany('PCL Construction');
        $crmSuperAdmin = $this->createCrmSuperAdmin($crmCompany);
        $pclSuperAdmin = $this->createCompanySuperAdmin($pclCompany, 'PCL Super Admin');
        $pclProject = $this->createProject($pclCompany, 'PRJ-PCL-01', 'PCL Bridge Project');

        $driver = Member::create([
            'name' => 'Driver Ramesh',
            'email' => 'ramesh.driver@example.com',
            'username' => 'driver_ramesh',
            'password' => Hash::make('password'),
            'slug' => 'driver-ramesh',
            'phone' => '9876543210',
            'status' => 1,
            'roles' => [6],
            'created_by' => $pclSuperAdmin->id,
            'company_name' => $pclCompany->name,
        ]);

        $vehicle = ConstructionVehicle::create([
            'project_id' => $pclProject->id,
            'vehicle_code' => 'VHC-PCL-001',
            'registration_number' => 'DL01AB9999',
            'vehicle_type' => 'Truck',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $pclSuperAdmin->id,
        ]);

        $assignment = VehicleAssignment::create([
            'project_id' => $pclProject->id,
            'vehicle_id' => $vehicle->id,
            'driver_member_id' => $driver->id,
            'assigned_from' => now(),
            'assigned_to' => now()->addDays(5),
            'status' => 'active',
            'assigned_by_type' => SuperAdmin::class,
            'assigned_by_id' => $pclSuperAdmin->id,
        ]);

        // 1. CRM Super Admin views Construction Vehicles assignments list
        $response = $this->actingAs($crmSuperAdmin, 'superadmin')->get(route('super.construction.vehicles.index'));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Construction/Vehicles/Index')
            ->has('assignments', 1)
            ->where('assignments.0.id', $assignment->id)
        );

        // 2. CRM Super Admin calls Dashboard API -> active vehicle assignment must be present
        \Laravel\Sanctum\Sanctum::actingAs($crmSuperAdmin, ['*']);
        $apiResponse = $this->getJson('/api/admin/dashboard');
        $apiResponse->assertOk();
        $data = $apiResponse->json('inventory.vehicles');
        $this->assertNotNull($data);
        $this->assertNotEmpty($data['active_assignments']);
        $this->assertEquals($assignment->id, $data['active_assignments'][0]['id']);
    }

    /**
     * Requirement 3A:
     * When assigning a vehicle, assigned_to date must be compulsory.
     */
    public function test_vehicle_assignment_requires_assigned_to_date(): void
    {
        $company = $this->createCompany('PCL Construction');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $project = $this->createProject($company, 'PRJ-PCL-02', 'Road Construction');

        $vehicle = ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => 'VHC-002',
            'registration_number' => 'DL02CD5678',
            'status' => 'active',
        ]);

        // Try assigning without assigned_to
        $res = $this->actingAs($superAdmin, 'superadmin')
            ->from(route('super.construction.vehicles.index'))
            ->post(route('super.construction.vehicles.assignments.store'), [
                'project_id' => $project->id,
                'vehicle_id' => $vehicle->id,
                'assigned_from' => now()->toDateTimeString(),
                'assigned_to' => '', // Empty!
            ]);

        $res->assertSessionHasErrors(['assigned_to']);
    }

    /**
     * Requirement 3B:
     * Total equipment count should not be duplicated when an equipment is added and allocated.
     */
    public function test_equipment_count_and_list_are_deduplicated(): void
    {
        $company = $this->createCompany('PCL Construction');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $project = $this->createProject($company, 'PRJ-PCL-03', 'Metro Project');

        $category = EquipmentCategory::create([
            'category_name' => 'Heavy Machinery',
            'status' => 1,
            'created_by' => $superAdmin->id,
            'company_id' => $company->id,
        ]);

        // Main equipment added
        $mainEquipment = Equipment::create([
            'equipment_id' => 'EQ-1001',
            'equipment_name' => 'Excavator 300',
            'category_id' => $category->id,
            'status' => 1,
            'assigned_project_id' => $project->id,
            'company_id' => $company->id,
            'created_by' => $superAdmin->id,
        ]);

        // Corresponding construction equipment copy created during project allocation
        $constEquipment = ConstructionEquipment::create([
            'project_id' => $project->id,
            'equipment_code' => 'EQ-1001',
            'name' => 'Excavator 300',
            'equipment_type' => 'Heavy Machinery',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $superAdmin->id,
        ]);

        // When viewing construction equipment workspace, equipments count should be 1 (not 2!)
        $response = $this->actingAs($superAdmin, 'superadmin')->get(route('super.construction.equipment.index'));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Construction/Equipment/Index')
            ->has('equipments', 1)
            ->where('equipments.0.equipment_code', 'EQ-1001')
        );
    }

    public function test_vehicle_assignment_auto_expires_when_assigned_to_passes_making_vehicle_free(): void
    {
        $company = $this->createCompany('PCL Construction');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $project = $this->createProject($company, 'PRJ-PCL-04', 'Highway Project');

        $vehicle = ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => 'VHC-004',
            'registration_number' => 'DL04XY9999',
            'status' => 'active',
        ]);

        // Create an assignment that ended in the past
        $assignment = VehicleAssignment::create([
            'project_id' => $project->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => now()->subDays(2),
            'assigned_to' => now()->subHour(1),
            'status' => 'active',
        ]);

        // When accessing vehicle workspace, the past assignment should auto-expire to inactive
        $response = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.construction.vehicles.index'));

        $response->assertOk();
        $this->assertEquals('inactive', $assignment->fresh()->status);
    }

    public function test_vehicle_assignment_blocks_overlapping_date_ranges(): void
    {
        $company = $this->createCompany('PCL Construction');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $project = $this->createProject($company, 'PRJ-PCL-05', 'Metro Phase 2');

        $vehicle = ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => 'VHC-005',
            'registration_number' => 'DL05ZZ1234',
            'status' => 'active',
        ]);

        // Active assignment from +1 hour to +5 hours
        VehicleAssignment::create([
            'project_id' => $project->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => now()->addHours(1),
            'assigned_to' => now()->addHours(5),
            'status' => 'active',
        ]);

        // Attempting to assign same vehicle for overlapping range (+2 to +6 hours) must be blocked!
        $response = $this->actingAs($superAdmin, 'superadmin')
            ->from(route('super.construction.vehicles.index'))
            ->post(route('super.construction.vehicles.assignments.store'), [
                'project_id' => $project->id,
                'vehicle_id' => $vehicle->id,
                'assigned_from' => now()->addHours(2)->toDateTimeString(),
                'assigned_to' => now()->addHours(6)->toDateTimeString(),
            ]);

        $response->assertSessionHasErrors(['vehicle_id']);

        // Non-overlapping range (+6 to +10 hours) should succeed
        $validResponse = $this->actingAs($superAdmin, 'superadmin')
            ->from(route('super.construction.vehicles.index'))
            ->post(route('super.construction.vehicles.assignments.store'), [
                'project_id' => $project->id,
                'vehicle_id' => $vehicle->id,
                'assigned_from' => now()->addHours(6)->toDateTimeString(),
                'assigned_to' => now()->addHours(10)->toDateTimeString(),
            ]);

        $validResponse->assertSessionHasNoErrors();
    }

    public function test_vehicle_assignment_can_be_returned_and_freed(): void
    {
        $company = $this->createCompany('PCL Construction');
        $superAdmin = $this->createCompanySuperAdmin($company);
        $project = $this->createProject($company, 'PRJ-PCL-06', 'Flyover Project');

        $vehicle = ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => 'VHC-006',
            'registration_number' => 'DL06AA0000',
            'status' => 'active',
        ]);

        $assignment = VehicleAssignment::create([
            'project_id' => $project->id,
            'vehicle_id' => $vehicle->id,
            'assigned_from' => now()->subHour(1),
            'assigned_to' => now()->addHours(5),
            'status' => 'active',
        ]);

        $res = $this->actingAs($superAdmin, 'superadmin')
            ->from(route('super.construction.vehicles.index'))
            ->post(route('super.construction.vehicles.assignments.return'), [
                'assignment_id' => $assignment->id,
            ]);

        $res->assertSessionHasNoErrors();
        $this->assertEquals('inactive', $assignment->fresh()->status);
    }
}
