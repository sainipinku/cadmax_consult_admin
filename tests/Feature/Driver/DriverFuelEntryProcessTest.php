<?php

namespace Tests\Feature\Driver;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionActivityLog;
use App\Models\ConstructionRole;
use App\Models\ConstructionVehicle;
use App\Models\DriverFuelEntry;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Models\VehicleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverFuelEntryProcessTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;
    private Member $driver;
    private Company $company;
    private Client $client;
    private Project $project;
    private ConstructionVehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->company = Company::create([
            'name' => 'FastFleet Logistics',
            'legal_name' => 'FastFleet Logistics Pvt Ltd',
            'email' => 'fleet@fastfleet.com',
            'phone' => '9876500000',
            'status' => 'active',
        ]);

        $this->admin = SuperAdmin::create([
            'name' => 'Fleet Super Admin',
            'email' => 'admin@fastfleet.com',
            'password' => Hash::make('password'),
            'company_id' => $this->company->id,
            'role' => 'admin',
            'status' => 1,
        ]);

        $this->client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Highway Express Authority',
            'email' => 'client@highway.com',
            'phone' => '9876511111',
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Highway Package 4',
            'project_code' => 'PRJ-HWY-04',
            'status' => 'active',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->addDays(90)->toDateString(),
        ]);

        $driverRole = ConstructionRole::firstOrCreate(
            ['slug' => 'vehicle_driver'],
            ['name' => 'Driver', 'role_type' => 'construction', 'status' => 'active']
        );

        $this->driver = Member::create([
            'name' => 'Surendra Driver',
            'slug' => 'surendra-driver',
            'username' => 'surendra_driver',
            'email' => 'surendra@fastfleet.com',
            'phone' => '9876540001',
            'password' => Hash::make('password'),
            'roles' => ['vehicle_driver'],
            'created_by' => $this->admin->id,
            'company_name' => $this->company->name,
            'status' => 1,
            'is_active' => true,
        ]);

        MemberRoleAssignment::create([
            'member_id' => $this->driver->id,
            'role_id' => $driverRole->id,
            'project_id' => $this->project->id,
            'status' => 1,
        ]);

        $this->vehicle = ConstructionVehicle::create([
            'project_id' => $this->project->id,
            'vehicle_code' => 'VHC-FF-01',
            'registration_number' => 'RJ14-GA-9999',
            'status' => 'active',
        ]);

        VehicleAssignment::create([
            'project_id' => $this->project->id,
            'vehicle_id' => $this->vehicle->id,
            'driver_member_id' => $this->driver->id,
            'assigned_from' => now()->subDays(5),
            'assigned_to' => now()->addDays(20),
            'status' => 'active',
        ]);
    }

    public function test_driver_can_submit_fuel_entry_with_complete_details_and_receipt(): void
    {
        $receipt = UploadedFile::fake()->image('fuel_slip.jpg', 600, 800);

        $payload = [
            'project_id' => $this->project->id,
            'vehicle_number' => 'RJ14-GA-9999',
            'fuel_type' => 'diesel',
            'quantity_liters' => 65.50,
            'total_amount' => 5895.00,
            'fuel_date' => now()->toDateString(),
            'odometer_reading' => 45210.5,
            'receipt_photo' => $receipt,
            'notes' => 'Filled full tank at Indian Oil pump bypass',
        ];

        $response = $this->actingAs($this->driver, 'sanctum')
            ->postJson('/api/member/driver-duty/fuel-entries', $payload);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.vehicle_number', 'RJ14-GA-9999');
        $this->assertEquals(65.50, (float) $response->json('data.quantity_liters'));
        $this->assertEquals(5895.00, (float) $response->json('data.total_amount'));
        $this->assertEquals(45210.5, (float) $response->json('data.odometer_reading'));
        $response->assertJsonPath('data.status', 'pending');
        $this->assertNotNull($response->json('data.receipt_photo_url'));

        // Assert record exists in database
        $this->assertDatabaseHas('driver_fuel_entries', [
            'driver_member_id' => $this->driver->id,
            'project_id' => $this->project->id,
            'vehicle_number' => 'RJ14-GA-9999',
            'quantity_liters' => 65.50,
            'total_amount' => 5895.00,
            'status' => 'pending',
        ]);

        // Assert activity log recorded
        $log = ConstructionActivityLog::where('module', 'fleet')
            ->where('action', 'driver_fuel_submitted')
            ->where('actor_id', $this->driver->id)
            ->first();

        $this->assertNotNull($log, 'Fuel entry submission must be logged in construction activity logs');
    }

    public function test_driver_can_list_fuel_history_and_see_approval_status(): void
    {
        DriverFuelEntry::create([
            'driver_member_id' => $this->driver->id,
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'vehicle_number' => 'RJ14-GA-9999',
            'fuel_type' => 'diesel',
            'quantity_liters' => 50.00,
            'total_amount' => 4500.00,
            'fuel_date' => now()->subDays(2)->toDateString(),
            'odometer_reading' => 44900.0,
            'status' => 'approved',
            'reviewed_by' => $this->admin->id,
            'admin_remarks' => 'Approved by accounts',
        ]);

        DriverFuelEntry::create([
            'driver_member_id' => $this->driver->id,
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'vehicle_number' => 'RJ14-GA-9999',
            'fuel_type' => 'diesel',
            'quantity_liters' => 30.00,
            'total_amount' => 2700.00,
            'fuel_date' => now()->toDateString(),
            'odometer_reading' => 45200.0,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->driver, 'sanctum')
            ->getJson('/api/member/driver-duty/fuel-entries');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data'));

        // Test filter by status=pending
        $filterResponse = $this->actingAs($this->driver, 'sanctum')
            ->getJson('/api/member/driver-duty/fuel-entries?status=pending');

        $filterResponse->assertOk();
        $this->assertCount(1, $filterResponse->json('data'));
        $this->assertEquals('pending', $filterResponse->json('data.0.status'));
    }

    public function test_driver_home_page_includes_fuel_history_and_summary_sections(): void
    {
        DriverFuelEntry::create([
            'driver_member_id' => $this->driver->id,
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'vehicle_number' => 'RJ14-GA-9999',
            'fuel_type' => 'diesel',
            'quantity_liters' => 40.00,
            'total_amount' => 3600.00,
            'fuel_date' => now()->subDay()->toDateString(),
            'odometer_reading' => 45000.0,
            'status' => 'approved',
        ]);

        DriverFuelEntry::create([
            'driver_member_id' => $this->driver->id,
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'vehicle_number' => 'RJ14-GA-9999',
            'fuel_type' => 'diesel',
            'quantity_liters' => 20.00,
            'total_amount' => 1800.00,
            'fuel_date' => now()->toDateString(),
            'odometer_reading' => 45150.0,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->driver, 'sanctum')
            ->getJson('/api/member/dashboard');

        $response->assertOk();
        $data = $response->json();

        // Fuel History on driver home page
        $this->assertArrayHasKey('fuel_history', $data);
        $this->assertCount(2, $data['fuel_history']);

        // Fuel summary metrics
        $this->assertArrayHasKey('fuel_summary', $data);
        $this->assertEquals(2, $data['fuel_summary']['total_entries']);
        $this->assertEquals(1, $data['fuel_summary']['pending_entries']);
        $this->assertEquals(1, $data['fuel_summary']['approved_entries']);
        $this->assertEquals(5400.00, (float) $data['fuel_summary']['total_amount']);
        $this->assertEquals(60.00, (float) $data['fuel_summary']['total_liters']);
    }

    public function test_admin_and_super_admin_can_review_verify_approve_fuel_entry(): void
    {
        $fuel = DriverFuelEntry::create([
            'driver_member_id' => $this->driver->id,
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'vehicle_number' => 'RJ14-GA-9999',
            'fuel_type' => 'diesel',
            'quantity_liters' => 55.00,
            'total_amount' => 4950.00,
            'fuel_date' => now()->toDateString(),
            'odometer_reading' => 45100.0,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'superadmin')
            ->postJson("/super/vehicles/fuel-entries/{$fuel->id}/verify", [
                'status' => 'approved',
                'admin_remarks' => 'Slip verified against Indian Oil portal',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'approved');

        $fuel->refresh();
        $this->assertEquals('approved', $fuel->status);
        $this->assertEquals('Slip verified against Indian Oil portal', $fuel->admin_remarks);
        $this->assertEquals($this->admin->id, $fuel->reviewed_by);
        $this->assertNotNull($fuel->reviewed_at);

        // Audit log verified
        $log = ConstructionActivityLog::where('module', 'fleet')
            ->where('action', 'driver_fuel_reviewed')
            ->first();
        $this->assertNotNull($log);
    }

    public function test_admin_and_super_admin_can_reject_fuel_entry_with_remarks(): void
    {
        $fuel = DriverFuelEntry::create([
            'driver_member_id' => $this->driver->id,
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'vehicle_number' => 'RJ14-GA-9999',
            'fuel_type' => 'diesel',
            'quantity_liters' => 80.00,
            'total_amount' => 7200.00,
            'fuel_date' => now()->toDateString(),
            'odometer_reading' => 45050.0,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'superadmin')
            ->postJson("/super/vehicles/fuel-entries/{$fuel->id}/verify", [
                'status' => 'rejected',
                'admin_remarks' => 'Receipt image unreadable, please re-upload clear slip',
            ]);

        $response->assertOk();
        $fuel->refresh();
        $this->assertEquals('rejected', $fuel->status);
        $this->assertEquals('Receipt image unreadable, please re-upload clear slip', $fuel->admin_remarks);
    }
}
