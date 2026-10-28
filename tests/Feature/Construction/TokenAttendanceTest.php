<?php

namespace Tests\Feature\Construction;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TokenAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(?int $companyId = null): SuperAdmin
    {
        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId,
            'roles' => json_encode(['super_admin']),
            'name' => 'Super Admin',
            'email' => 'admin-' . Str::random(5) . '@example.com',
            'phone' => '9999999999',
            'whatsapp_phone' => '9999999999',
            'password' => 'password',
        ]);
    }

    private function createMember(SuperAdmin $superAdmin, string $name, ?int $companyId = null): Member
    {
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(5));

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'company_id' => $companyId,
            'name' => $name,
            'username' => $slug,
            'email' => $slug . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'slug' => $slug,
        ]);
    }

    private function createProject(string $name, Company $company): Project
    {
        $client = \App\Models\Client::create([
            'company_id' => $company->id,
            'client_code' => 'CLI-' . strtoupper(Str::random(4)),
            'name' => $name . ' Client',
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . strtoupper(Str::random(4)),
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function test_token_based_check_in_creates_record_for_authenticated_member(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp', 'status' => 'active']);
        $project = $this->createProject('Site A', $company);
        $member = $this->createMember($admin, 'John Doe', $company->id);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/attendance/check-in', [
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'accuracy' => 15.5,
            'notes' => 'Starting shift',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('attendance.member_id', $member->id);
        $response->assertJsonPath('attendance.project_id', $project->id);

        $this->assertDatabaseHas('construction_attendance_records', [
            'member_id' => $member->id,
            'project_id' => $project->id,
        ]);
    }

    public function test_token_based_check_in_resolves_project_from_role_assignment(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp', 'status' => 'active']);
        $project = $this->createProject('Site C', $company);
        $member = $this->createMember($admin, 'Assigned Member', $company->id);
        $role = ConstructionRole::create([
            'name' => 'Site Employee',
            'slug' => 'site-employee',
            'description' => 'Site employee role',
            'is_system_role' => true,
            'status' => 'active',
        ]);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $role->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/attendance/check-in');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('attendance.project_id', $project->id);
    }

    public function test_token_based_check_in_resolves_project_from_creator_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other Corp', 'status' => 'active']);
        $otherProject = $this->createProject('Other Site', $otherCompany);
        $company = Company::create(['name' => 'Acme Corp', 'status' => 'active']);
        $admin = $this->createSuperAdmin($company->id);
        $project = $this->createProject('Assigned Site', $company);
        $member = $this->createMember($admin, 'Creator Company Member');

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/attendance/check-in');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('attendance.project_id', $project->id);
        $this->assertNotSame($otherProject->id, $response->json('attendance.project_id'));
    }

    public function test_token_based_check_out_without_attendance_id_resolves_open_check_in(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp', 'status' => 'active']);
        $project = $this->createProject('Site B', $company);
        $member = $this->createMember($admin, 'Jane Doe', $company->id);

        $record = AttendanceRecord::create([
            'member_id' => $member->id,
            'project_id' => $project->id,
            'attendance_date' => now()->toDateString(),
            'check_in_at' => now()->subHours(4),
            'check_in_latitude' => 28.6139,
            'check_in_longitude' => 77.2090,
            'status' => 'submitted',
        ]);

        // Calling check-out without attendance ID in URL
        $response = $this->actingAs($member, 'sanctum')->postJson('/api/attendance/check-out', [
            'latitude' => 28.6140,
            'longitude' => 77.2092,
            'notes' => 'Ending shift',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('attendance.id', $record->id);
        $this->assertNotNull($response->json('attendance.check_out_at'));

        $record->refresh();
        $this->assertNotNull($record->check_out_at);
        $this->assertEquals(28.6140, (float) $record->check_out_latitude);
    }

    public function test_token_based_check_out_with_attendance_id_in_url(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp', 'status' => 'active']);
        $project = $this->createProject('Site C', $company);
        $member = $this->createMember($admin, 'Mark Smith', $company->id);

        $record = AttendanceRecord::create([
            'member_id' => $member->id,
            'project_id' => $project->id,
            'attendance_date' => now()->toDateString(),
            'check_in_at' => now()->subHours(2),
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($member, 'sanctum')->postJson("/api/attendance/{$record->id}/check-out", [
            'latitude' => 28.6140,
            'longitude' => 77.2092,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('attendance.id', $record->id);

        $record->refresh();
        $this->assertNotNull($record->check_out_at);
    }

    public function test_check_out_prevents_checking_out_another_members_record(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp', 'status' => 'active']);
        $project = $this->createProject('Site D', $company);
        $memberA = $this->createMember($admin, 'Member A', $company->id);
        $memberB = $this->createMember($admin, 'Member B', $company->id);

        $recordB = AttendanceRecord::create([
            'member_id' => $memberB->id,
            'project_id' => $project->id,
            'attendance_date' => now()->toDateString(),
            'check_in_at' => now()->subHours(3),
            'status' => 'submitted',
        ]);

        // Member A tries to check out Member B's record
        $response = $this->actingAs($memberA, 'sanctum')->postJson("/api/attendance/{$recordB->id}/check-out", [
            'latitude' => 28.6140,
            'longitude' => 77.2092,
        ]);

        $response->assertForbidden();
    }

    public function test_repeated_check_in_returns_already_checked_in_message(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp', 'status' => 'active']);
        $project = $this->createProject('Site E', $company);
        $member = $this->createMember($admin, 'Alice', $company->id);

        $record = AttendanceRecord::create([
            'member_id' => $member->id,
            'project_id' => $project->id,
            'attendance_date' => now()->toDateString(),
            'check_in_at' => now()->subHour(),
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/attendance/check-in');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('attendance.id', $record->id);
        $this->assertStringContainsString('Already checked in', $response->json('message'));
    }

    public function test_repeated_check_out_returns_already_checked_out_message(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp', 'status' => 'active']);
        $project = $this->createProject('Site F', $company);
        $member = $this->createMember($admin, 'Bob', $company->id);

        $record = AttendanceRecord::create([
            'member_id' => $member->id,
            'project_id' => $project->id,
            'attendance_date' => now()->toDateString(),
            'check_in_at' => now()->subHours(8),
            'check_out_at' => now()->subHour(),
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($member, 'sanctum')->postJson('/api/attendance/check-out');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertStringContainsString('Already checked out', $response->json('message'));
    }

    public function test_member_prefixed_routes_work_identically(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp', 'status' => 'active']);
        $project = $this->createProject('Site G', $company);
        $member = $this->createMember($admin, 'Charlie', $company->id);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        // Check in via /api/member/attendance/check-in
        $checkInRes = $this->actingAs($member, 'sanctum')->postJson('/api/member/attendance/check-in', [
            'latitude' => 19.0760,
            'longitude' => 72.8777,
        ]);
        $checkInRes->assertOk();
        $checkInRes->assertJsonPath('success', true);

        // Check out via /api/member/attendance/check-out
        $checkOutRes = $this->actingAs($member, 'sanctum')->postJson('/api/member/attendance/check-out', [
            'latitude' => 19.0762,
            'longitude' => 72.8779,
        ]);
        $checkOutRes->assertOk();
        $checkOutRes->assertJsonPath('success', true);
    }
}
