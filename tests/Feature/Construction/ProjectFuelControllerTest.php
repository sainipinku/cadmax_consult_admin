<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\ConstructionVehicle;
use App\Models\DriverFuelEntry;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectFuelControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ConstructionRole::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Full System Access', 'is_system_role' => true, 'status' => 'active']
        );
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $response = $this->get('/super/construction/fuel-history');
        $response->assertRedirect();
    }

    public function test_superadmin_project_owner_can_access_fuel_history_index_and_sees_all_projects_and_fuel_logs(): void
    {
        $owner = SuperAdmin::firstOrCreate(
            ['email' => 'owner_fuel_test@cadmax.com'],
            ['name' => 'Owner Fuel Test', 'password' => bcrypt('password123'), 'status' => 1]
        );
        $owner->company_id = null;
        $owner->save();

        $companyA = Company::firstOrCreate(['name' => 'Fuel Company Alpha']);
        $companyB = Company::firstOrCreate(['name' => 'Fuel Company Beta']);

        $projA = Project::firstOrCreate(
            ['project_code' => 'PRJ-FUEL-A1'],
            ['name' => 'Site Fuel A1', 'company_id' => $companyA->id, 'status' => 'active']
        );
        $projB = Project::firstOrCreate(
            ['project_code' => 'PRJ-FUEL-B1'],
            ['name' => 'Site Fuel B1', 'company_id' => $companyB->id, 'status' => 'active']
        );

        $driver = Member::firstOrCreate(
            ['email' => 'driver_a1@cadmax.com'],
            [
                'name' => 'Driver Alpha 1',
                'username' => 'driver_a1',
                'slug' => 'driver_a1',
                'created_by' => $owner->id,
                'password' => bcrypt('password123'),
                'status' => 1,
            ]
        );

        $fuelA = DriverFuelEntry::create([
            'driver_member_id' => $driver->id,
            'company_id' => $companyA->id,
            'project_id' => $projA->id,
            'vehicle_number' => 'MH01AA1111',
            'fuel_type' => 'Diesel',
            'quantity_liters' => 50,
            'total_amount' => 4500,
            'fuel_date' => Carbon::today(),
            'odometer_reading' => 12000,
            'status' => 'pending',
        ]);

        $fuelB = DriverFuelEntry::create([
            'driver_member_id' => $driver->id,
            'company_id' => $companyB->id,
            'project_id' => $projB->id,
            'vehicle_number' => 'DL02BB2222',
            'fuel_type' => 'Petrol',
            'quantity_liters' => 30,
            'total_amount' => 3200,
            'fuel_date' => Carbon::today(),
            'odometer_reading' => 25000,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($owner, 'superadmin')
            ->get('/super/construction/fuel-history');

        $response->assertOk();
        $response->assertInertia(function ($page) use ($projA, $projB) {
            $page->component('SuperAdmin/Construction/Fuel/Index')
                ->where('isProjectOwner', true)
                ->has('projects')
                ->has('fuelEntries');
        });
    }

    public function test_company_superadmin_is_scoped_strictly_to_own_company_projects_and_fuel_logs(): void
    {
        $companyA = Company::firstOrCreate(['name' => 'Scoped Fuel Co A']);
        $companyB = Company::firstOrCreate(['name' => 'Scoped Fuel Co B']);

        $companySuperAdmin = SuperAdmin::firstOrCreate(
            ['email' => 'scoped_admin_a@alpha.com'],
            ['name' => 'Scoped Admin A', 'password' => bcrypt('password123'), 'company_id' => $companyA->id, 'status' => 1]
        );
        $companySuperAdmin->company_id = $companyA->id;
        $companySuperAdmin->save();

        $projA = Project::firstOrCreate(
            ['project_code' => 'PRJ-SCOPED-A'],
            ['name' => 'Scoped Site A', 'company_id' => $companyA->id, 'status' => 'active']
        );
        $projB = Project::firstOrCreate(
            ['project_code' => 'PRJ-SCOPED-B'],
            ['name' => 'Scoped Site B', 'company_id' => $companyB->id, 'status' => 'active']
        );

        $driverA = Member::firstOrCreate(
            ['email' => 'scoped_driver_a@alpha.com'],
            [
                'name' => 'Scoped Driver A',
                'username' => 'scoped_driver_a',
                'slug' => 'scoped_driver_a',
                'created_by' => $companySuperAdmin->id,
                'password' => bcrypt('password123'),
                'status' => 1,
            ]
        );

        $fuelA = DriverFuelEntry::create([
            'driver_member_id' => $driverA->id,
            'company_id' => $companyA->id,
            'project_id' => $projA->id,
            'vehicle_number' => 'MH12AA3333',
            'fuel_type' => 'Diesel',
            'quantity_liters' => 40,
            'total_amount' => 3800,
            'fuel_date' => Carbon::today(),
            'odometer_reading' => 15000,
            'status' => 'pending',
        ]);

        $fuelB = DriverFuelEntry::create([
            'driver_member_id' => $driverA->id,
            'company_id' => $companyB->id,
            'project_id' => $projB->id,
            'vehicle_number' => 'DL04BB4444',
            'fuel_type' => 'Diesel',
            'quantity_liters' => 25,
            'total_amount' => 2400,
            'fuel_date' => Carbon::today(),
            'odometer_reading' => 18000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($companySuperAdmin, 'superadmin')
            ->get('/super/construction/fuel-history');

        $response->assertOk();
        $response->assertInertia(function ($page) use ($projA, $fuelA, $fuelB) {
            $page->component('SuperAdmin/Construction/Fuel/Index')
                ->where('isProjectOwner', false);

            $props = $page->toArray()['props'];
            $projectIds = collect($props['projects'])->pluck('id')->all();
            $this->assertContains($projA->id, $projectIds);

            $fuelIds = collect($props['fuelEntries'])->pluck('id')->all();
            $this->assertContains($fuelA->id, $fuelIds);
            $this->assertNotContains($fuelB->id, $fuelIds);
        });
    }

    public function test_fuel_detail_endpoint_returns_json_modal_payload(): void
    {
        $owner = SuperAdmin::firstOrCreate(
            ['email' => 'owner_detail_test@cadmax.com'],
            ['name' => 'Owner Detail Test', 'password' => bcrypt('password123'), 'status' => 1]
        );
        $owner->company_id = null;
        $owner->save();

        $company = Company::firstOrCreate(['name' => 'Detail Test Co']);
        $proj = Project::firstOrCreate(
            ['project_code' => 'PRJ-DETAIL-1'],
            ['name' => 'Detail Site 1', 'company_id' => $company->id, 'status' => 'active']
        );

        $driver = Member::firstOrCreate(
            ['email' => 'detail_driver@cadmax.com'],
            [
                'name' => 'Detail Driver',
                'username' => 'detail_driver',
                'slug' => 'detail_driver',
                'created_by' => $owner->id,
                'password' => bcrypt('password123'),
                'status' => 1,
            ]
        );

        $fuel = DriverFuelEntry::create([
            'driver_member_id' => $driver->id,
            'company_id' => $company->id,
            'project_id' => $proj->id,
            'vehicle_number' => 'MH14DE9999',
            'fuel_type' => 'Diesel',
            'quantity_liters' => 60,
            'total_amount' => 5400,
            'fuel_date' => Carbon::today(),
            'odometer_reading' => 30000,
            'receipt_photo_url' => 'https://example.com/receipt.jpg',
            'status' => 'pending',
            'notes' => 'Full tank filling at highway pump',
        ]);

        $response = $this->actingAs($owner, 'superadmin')
            ->getJson("/super/construction/fuel-history/{$fuel->id}");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $fuel->id,
                'vehicle_number' => 'MH14DE9999',
                'fuel_type' => 'Diesel',
                'quantity_liters' => 60,
                'total_amount' => 5400,
                'rate_per_liter' => 90.0,
                'odometer_reading' => 30000,
                'receipt_photo_url' => 'https://example.com/receipt.jpg',
                'notes' => 'Full tank filling at highway pump',
                'status' => 'pending',
            ],
        ]);
    }

    public function test_fuel_verify_endpoint_updates_status_remarks_and_reviewer(): void
    {
        $owner = SuperAdmin::firstOrCreate(
            ['email' => 'owner_verify_test@cadmax.com'],
            ['name' => 'Owner Verify Test', 'password' => bcrypt('password123'), 'status' => 1]
        );
        $owner->company_id = null;
        $owner->save();

        $company = Company::firstOrCreate(['name' => 'Verify Test Co']);
        $proj = Project::firstOrCreate(
            ['project_code' => 'PRJ-VERIFY-1'],
            ['name' => 'Verify Site 1', 'company_id' => $company->id, 'status' => 'active']
        );

        $driver = Member::firstOrCreate(
            ['email' => 'verify_driver@cadmax.com'],
            [
                'name' => 'Verify Driver',
                'username' => 'verify_driver',
                'slug' => 'verify_driver',
                'created_by' => $owner->id,
                'password' => bcrypt('password123'),
                'status' => 1,
            ]
        );

        $fuel = DriverFuelEntry::create([
            'driver_member_id' => $driver->id,
            'company_id' => $company->id,
            'project_id' => $proj->id,
            'vehicle_number' => 'MH14DE8888',
            'fuel_type' => 'Diesel',
            'quantity_liters' => 50,
            'total_amount' => 4500,
            'fuel_date' => Carbon::today(),
            'odometer_reading' => 20000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($owner, 'superadmin')
            ->post("/super/construction/fuel-history/{$fuel->id}/verify", [
                'status' => 'approved',
                'admin_remarks' => 'Receipt verified and odometer matched.',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('driver_fuel_entries', [
            'id' => $fuel->id,
            'status' => 'approved',
            'admin_remarks' => 'Receipt verified and odometer matched.',
            'reviewed_by' => $owner->id,
        ]);
    }

    public function test_company_superadmin_cannot_access_or_verify_other_company_fuel_entry(): void
    {
        $companyA = Company::firstOrCreate(['name' => 'Alpha Co Tenant']);
        $companyB = Company::firstOrCreate(['name' => 'Beta Co Tenant']);

        $adminA = SuperAdmin::firstOrCreate(
            ['email' => 'admin_a_idor@alpha.com'],
            ['name' => 'Admin A IDOR', 'password' => bcrypt('password123'), 'company_id' => $companyA->id, 'status' => 1]
        );
        $adminA->company_id = $companyA->id;
        $adminA->save();

        $projB = Project::firstOrCreate(
            ['project_code' => 'PRJ-IDOR-B'],
            ['name' => 'IDOR Site B', 'company_id' => $companyB->id, 'status' => 'active']
        );

        $driverB = Member::firstOrCreate(
            ['email' => 'driver_b_idor@beta.com'],
            [
                'name' => 'Driver B IDOR',
                'username' => 'driver_b_idor',
                'slug' => 'driver_b_idor',
                'created_by' => $adminA->id,
                'password' => bcrypt('password123'),
                'status' => 1,
            ]
        );

        $fuelB = DriverFuelEntry::create([
            'driver_member_id' => $driverB->id,
            'company_id' => $companyB->id,
            'project_id' => $projB->id,
            'vehicle_number' => 'MH20BB5555',
            'fuel_type' => 'Petrol',
            'quantity_liters' => 35,
            'total_amount' => 3500,
            'fuel_date' => Carbon::today(),
            'odometer_reading' => 22000,
            'status' => 'pending',
        ]);

        // Attempting to show or verify fuel entry of company B must return 403 Forbidden
        $showResponse = $this->actingAs($adminA, 'superadmin')
            ->getJson("/super/construction/fuel-history/{$fuelB->id}");
        $showResponse->assertStatus(403);

        $verifyResponse = $this->actingAs($adminA, 'superadmin')
            ->post("/super/construction/fuel-history/{$fuelB->id}/verify", [
                'status' => 'approved',
                'admin_remarks' => 'Tampered approval attempt',
            ]);
        $verifyResponse->assertStatus(403);

        // Assert record remained pending
        $this->assertEquals('pending', $fuelB->fresh()->status);
    }
}

