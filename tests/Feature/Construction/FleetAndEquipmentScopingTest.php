<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionVehicle;
use App\Models\EquipmentCategory;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FleetAndEquipmentScopingTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(string $name): Company
    {
        return Company::create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function createSuperAdmin(string $name, Company $company): SuperAdmin
    {
        static $counter = 10;
        $counter++;

        return SuperAdmin::create([
            'name' => $name,
            'email' => "admin{$counter}@example.com",
            'username' => "admin{$counter}",
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

    public function test_vehicle_management_is_scoped_by_super_admin_company(): void
    {
        $company1 = $this->createCompany('Company One');
        $company2 = $this->createCompany('Company Two');

        $admin1 = $this->createSuperAdmin('Admin One', $company1);
        $admin2 = $this->createSuperAdmin('Admin Two', $company2);

        $vehicle1 = Vehicle::create([
            'vehicle_name' => 'Truck 101',
            'vehicle_number' => 'MH12AA1111',
            'vehicle_type' => 'Truck',
            'brand' => 'Tata',
            'fuel_type' => 'Diesel',
            'status' => 1,
            'created_by' => $admin1->id,
            'company_id' => $company1->id,
        ]);

        $vehicle2 = Vehicle::create([
            'vehicle_name' => 'Car 202',
            'vehicle_number' => 'MH12BB2222',
            'vehicle_type' => 'Car',
            'brand' => 'Mahindra',
            'fuel_type' => 'Diesel',
            'status' => 1,
            'created_by' => $admin2->id,
            'company_id' => $company2->id,
        ]);

        // Admin 1 index check
        $res1 = $this->actingAs($admin1, 'superadmin')->get(route('super.vehicles.list'));
        $res1->assertOk();
        $res1->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Vehicles/List')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.id', $vehicle1->id)
        );

        // Admin 2 index check
        $res2 = $this->actingAs($admin2, 'superadmin')->get(route('super.vehicles.list'));
        $res2->assertOk();
        $res2->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Vehicles/List')
            ->has('vehicles.data', 1)
            ->where('vehicles.data.0.id', $vehicle2->id)
        );

        // Admin 1 cannot view or modify Admin 2's vehicle
        $this->actingAs($admin1, 'superadmin')
            ->get(route('super.vehicles.show', $vehicle2->uuid))
            ->assertNotFound();

        $this->actingAs($admin1, 'superadmin')
            ->delete(route('super.vehicles.destroy', $vehicle2->uuid))
            ->assertNotFound();
    }

    public function test_vehicle_creation_stores_actor_and_company(): void
    {
        $company = $this->createCompany('Alpha Builders');
        $admin = $this->createSuperAdmin('Alpha Admin', $company);

        $response = $this->actingAs($admin, 'superadmin')->post(route('super.vehicles.store'), [
            'vehicle_name' => 'Alpha Dumper',
            'vehicle_number' => 'DL01AB9999',
            'vehicle_type' => 'Truck',
            'brand' => 'Ashok Leyland',
            'fuel_type' => 'Diesel',
            'status' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $vehicle = Vehicle::where('vehicle_number', 'DL01AB9999')->first();
        $this->assertNotNull($vehicle);
        $this->assertEquals($admin->id, $vehicle->created_by);
        $this->assertEquals($company->id, $vehicle->company_id);
    }

    public function test_equipment_category_management_is_scoped_by_super_admin_company(): void
    {
        $company1 = $this->createCompany('Company One');
        $company2 = $this->createCompany('Company Two');

        $admin1 = $this->createSuperAdmin('Admin One', $company1);
        $admin2 = $this->createSuperAdmin('Admin Two', $company2);

        $cat1 = EquipmentCategory::create([
            'category_id' => 'EC-111111',
            'category_name' => 'Heavy Excavation',
            'status' => 1,
            'created_by' => $admin1->id,
            'company_id' => $company1->id,
        ]);

        $cat2 = EquipmentCategory::create([
            'category_id' => 'EC-222222',
            'category_name' => 'Survey Tools',
            'status' => 1,
            'created_by' => $admin2->id,
            'company_id' => $company2->id,
        ]);

        // Admin 1 index check
        $res1 = $this->actingAs($admin1, 'superadmin')->get(route('super.equipment.categories.list'));
        $res1->assertOk();
        $res1->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/EquipmentCategories/List')
            ->has('categories.data', 1)
            ->where('categories.data.0.id', $cat1->id)
        );

        // Admin 2 cannot view or modify Admin 1's category
        $this->actingAs($admin2, 'superadmin')
            ->get(route('super.equipment.categories.show', $cat1->id))
            ->assertNotFound();

        $this->actingAs($admin2, 'superadmin')
            ->delete(route('super.equipment.categories.destroy', $cat1->id))
            ->assertNotFound();
    }

    public function test_equipment_category_creation_stores_actor_and_company(): void
    {
        $company = $this->createCompany('Alpha Builders');
        $admin = $this->createSuperAdmin('Alpha Admin', $company);

        $response = $this->actingAs($admin, 'superadmin')->post(route('super.equipment.categories.store'), [
            'category_name' => 'Concrete Mixers',
            'description' => 'Various mixing equipment',
            'status' => 1,
        ]);

        $response->assertRedirect(route('super.equipment.categories.list'));

        $category = EquipmentCategory::where('category_name', 'Concrete Mixers')->first();
        $this->assertNotNull($category);
        $this->assertEquals($admin->id, $category->created_by);
        $this->assertEquals($company->id, $category->company_id);
    }

    public function test_vehicle_tracking_workspace_is_scoped_by_super_admin_company(): void
    {
        $company1 = $this->createCompany('Company One');
        $company2 = $this->createCompany('Company Two');

        $admin1 = $this->createSuperAdmin('Admin One', $company1);
        $admin2 = $this->createSuperAdmin('Admin Two', $company2);

        $project1 = $this->createProject($company1, 'PRJ-101', 'Highway Project');
        $project2 = $this->createProject($company2, 'PRJ-202', 'Airport Project');

        $driver1 = Member::create([
            'name' => 'Driver 1',
            'username' => 'driver1',
            'email' => 'driver1@example.com',
            'password' => Hash::make('password'),
            'created_by' => $admin1->id,
            'slug' => 'driver-1',
            'status' => 1,
            'driver_duty_status' => 'available',
        ]);

        $mainVehicle1 = Vehicle::create([
            'vehicle_name' => 'Tipper 1',
            'vehicle_number' => 'MH12AA5555',
            'vehicle_type' => 'Truck',
            'brand' => 'Tata',
            'fuel_type' => 'Diesel',
            'status' => 1,
            'created_by' => $admin1->id,
            'company_id' => $company1->id,
        ]);

        $constructionVehicle1 = ConstructionVehicle::create([
            'project_id' => $project1->id,
            'vehicle_code' => 'VHC-000001',
            'registration_number' => 'MH12AA5555',
            'model_name' => 'Tipper 1',
            'status' => 'active',
        ]);

        $assignment1 = VehicleAssignment::create([
            'project_id' => $project1->id,
            'vehicle_id' => $constructionVehicle1->id,
            'driver_member_id' => $driver1->id,
            'assigned_from' => now()->subDay(),
            'status' => 'active',
        ]);

        // When Admin 1 accesses Vehicle Tracking workspace
        $res1 = $this->actingAs($admin1, 'superadmin')->get(route('super.construction.vehicles.index'));
        $res1->assertOk();
        $res1->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Construction/Vehicles/Index')
            ->has('projects', 1)
            ->where('projects.0.id', $project1->id)
            ->has('vehicles', 2)
            ->where('vehicles.0.id', $mainVehicle1->id)
            ->has('assignments', 1)
            ->where('assignments.0.id', $assignment1->id)
        );

        // When Admin 2 accesses Vehicle Tracking workspace, they see neither project1 nor assignment1
        $res2 = $this->actingAs($admin2, 'superadmin')->get(route('super.construction.vehicles.index'));
        $res2->assertOk();
        $res2->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Construction/Vehicles/Index')
            ->has('projects', 1)
            ->where('projects.0.id', $project2->id)
            ->has('vehicles', 0)
            ->has('assignments', 0)
        );
    }
}
