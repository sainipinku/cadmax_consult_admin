<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\EmployeeLocation;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeLocationAttendanceTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(): Member
    {
        $superAdmin = SuperAdmin::firstOrCreate(
            ['email' => 'admin-location@example.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Admin Location',
                'phone' => '9999999999',
                'whatsapp_phone' => '9999999999',
                'password' => 'password',
            ]
        );

        $company = Company::create(['name' => 'CadMax Tests', 'status' => 'active']);
        $username = 'member-' . Str::random(6);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'company_id' => $company->id,
            'name' => 'Tracking User',
            'username' => $username,
            'slug' => $username,
            'email' => $username . '@example.com',
            'phone' => '9' . random_int(100000000, 999999999),
            'status' => Member::STATUS_ACTIVE,
            'password' => 'password',
        ]);

        $client = \App\Models\Client::create([
            'company_id' => $company->id,
            'client_code' => 'CLI-' . strtoupper(Str::random(4)),
            'name' => 'Test Client',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . strtoupper(Str::random(4)),
            'slug' => 'test-project-' . Str::random(4),
            'name' => 'Test Project',
            'status' => 'active',
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        return $member;
    }

    public function test_check_in_creates_location_entry_and_saves_last_lat_long_to_member(): void
    {
        $member = $this->createMember();

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/member/attendance/check-in', [
            'latitude' => 26.9124,
            'longitude' => 75.7873,
            'accuracy' => 15.0,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Assert employee_locations has the entry
        $this->assertDatabaseHas('employee_locations', [
            'member_id' => $member->id,
            'latitude' => 26.9124,
            'longitude' => 75.7873,
        ]);

        // Assert members table has latest lat and long
        $member->refresh();
        $this->assertEquals(26.9124, (float) $member->latitude);
        $this->assertEquals(75.7873, (float) $member->longitude);
        $this->assertNotNull($member->location_captured_at);
    }

    public function test_check_out_creates_location_entry_and_saves_last_lat_long_to_member(): void
    {
        $member = $this->createMember();

        // Perform initial check in
        $this->actingAs($member, 'sanctum')->postJson('/api/member/attendance/check-in', [
            'latitude' => 26.9100,
            'longitude' => 75.7800,
        ]);

        $record = AttendanceRecord::where('member_id', $member->id)->latest('id')->first();
        $this->assertNotNull($record);

        // Perform check out with specific attendance ID
        $response = $this->actingAs($member, 'sanctum')->postJson("/api/member/attendance/{$record->id}/check-out", [
            'latitude' => 26.9200,
            'longitude' => 75.7900,
            'accuracy' => 8.5,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Assert employee_locations has checkout entry
        $this->assertDatabaseHas('employee_locations', [
            'member_id' => $member->id,
            'latitude' => 26.9200,
            'longitude' => 75.7900,
        ]);

        // Assert members table has updated last coordinates from checkout
        $member->refresh();
        $this->assertEquals(26.9200, (float) $member->latitude);
        $this->assertEquals(75.7900, (float) $member->longitude);
    }

    public function test_employee_locations_table_only_carries_today_and_yesterday_and_auto_deletes_older_data(): void
    {
        $member = $this->createMember();

        // 1. Manually insert older records (3 days ago, 5 days ago)
        EmployeeLocation::create([
            'member_id' => $member->id,
            'latitude' => 25.1000,
            'longitude' => 75.1000,
            'accuracy' => 10,
            'captured_at' => now()->subDays(5),
        ]);
        EmployeeLocation::create([
            'member_id' => $member->id,
            'latitude' => 25.2000,
            'longitude' => 75.2000,
            'accuracy' => 10,
            'captured_at' => now()->subDays(3),
        ]);

        // 2. Insert yesterday's record (noon yesterday in Asia/Kolkata, normalized to UTC)
        $yesterdayLocation = EmployeeLocation::create([
            'member_id' => $member->id,
            'latitude' => 25.3000,
            'longitude' => 75.3000,
            'accuracy' => 10,
            'captured_at' => now('Asia/Kolkata')->subDay()->startOfDay()->addHours(12)->setTimezone('UTC'),
        ]);

        $this->assertEquals(3, EmployeeLocation::count());

        // 3. New check-in today should trigger auto-pruning
        $response = $this->actingAs($member, 'sanctum')->postJson('/api/member/attendance/check-in', [
            'latitude' => 26.9500,
            'longitude' => 75.8500,
        ]);

        $response->assertOk();

        // Only yesterday's and today's records should remain (2 total)
        $this->assertEquals(2, EmployeeLocation::count());

        // Verify older records were deleted
        $this->assertDatabaseMissing('employee_locations', ['latitude' => 25.1000]);
        $this->assertDatabaseMissing('employee_locations', ['latitude' => 25.2000]);

        // Verify yesterday's and today's records are kept
        $this->assertDatabaseHas('employee_locations', ['id' => $yesterdayLocation->id]);
        $this->assertDatabaseHas('employee_locations', ['latitude' => 26.9500, 'longitude' => 75.8500]);
    }
}
