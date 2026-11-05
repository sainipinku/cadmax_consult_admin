<?php

namespace Tests\Feature\Construction;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectAttendanceControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $superAdminRole = ConstructionRole::firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Full System Access', 'is_system_role' => true, 'status' => 'active']
        );

        (new \Database\Seeders\AttendancePermissionSeeder())->run();
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $response = $this->get('/super/construction/attendance');
        $response->assertRedirect();
    }

    public function test_superadmin_project_owner_can_access_attendance_index_and_sees_all_projects(): void
    {
        $owner = SuperAdmin::firstOrCreate(
            ['email' => 'projectowner_test@cadmax.com'],
            [
                'name' => 'Project Owner Test',
                'password' => bcrypt('password123'),
                'status' => 1,
            ]
        );
        $owner->company_id = null;
        $owner->save();

        $companyA = Company::firstOrCreate(['name' => 'Alpha Constructions Test']);
        $companyB = Company::firstOrCreate(['name' => 'Beta Builders Test']);

        $projA = Project::firstOrCreate(
            ['project_code' => 'PRJ-ALPHA-1'],
            ['name' => 'Alpha Site 1', 'company_id' => $companyA->id, 'status' => 'active']
        );
        $projB = Project::firstOrCreate(
            ['project_code' => 'PRJ-BETA-1'],
            ['name' => 'Beta Site 1', 'company_id' => $companyB->id, 'status' => 'active']
        );

        $response = $this->actingAs($owner, 'superadmin')
            ->get('/super/construction/attendance');

        $response->assertOk();
        $response->assertInertia(function ($page) use ($projA, $projB) {
            $page->component('SuperAdmin/Construction/Attendance/Index')
                ->where('isProjectOwner', true)
                ->has('projects')
                ->has('attendanceRecords');
        });
    }

    public function test_company_superadmin_is_scoped_strictly_to_own_company_projects(): void
    {
        $companyA = Company::firstOrCreate(['name' => 'Alpha Constructions Test']);
        $companyB = Company::firstOrCreate(['name' => 'Beta Builders Test']);

        $companySuperAdmin = SuperAdmin::firstOrCreate(
            ['email' => 'companyadmin_test@alpha.com'],
            [
                'name' => 'Alpha Admin Test',
                'password' => bcrypt('password123'),
                'company_id' => $companyA->id,
                'status' => 1,
            ]
        );
        $companySuperAdmin->company_id = $companyA->id;
        $companySuperAdmin->save();

        $projA = Project::firstOrCreate(
            ['project_code' => 'PRJ-ALPHA-2'],
            ['name' => 'Alpha Site 2', 'company_id' => $companyA->id, 'status' => 'active']
        );
        $projB = Project::firstOrCreate(
            ['project_code' => 'PRJ-BETA-2'],
            ['name' => 'Beta Site 2', 'company_id' => $companyB->id, 'status' => 'active']
        );

        $response = $this->actingAs($companySuperAdmin, 'superadmin')
            ->get('/super/construction/attendance');

        $response->assertOk();
        $response->assertInertia(function ($page) use ($projA, $projB) {
            $page->component('SuperAdmin/Construction/Attendance/Index')
                ->where('isProjectOwner', false);

            $projects = collect($page->toArray()['props']['projects']);
            $this->assertTrue($projects->contains('id', $projA->id));
            $this->assertFalse($projects->contains('id', $projB->id));
        });
    }

    public function test_attendance_detail_endpoint_returns_json_with_coordinates_and_hours(): void
    {
        $owner = SuperAdmin::firstOrCreate(
            ['email' => 'projectowner_test@cadmax.com'],
            ['name' => 'Project Owner Test', 'password' => bcrypt('password123'), 'status' => 1]
        );
        $owner->company_id = null;
        $owner->save();

        $company = Company::firstOrCreate(['name' => 'Jaipur Metro Infra']);
        $project = Project::firstOrCreate(
            ['project_code' => 'PRJ-JPR-10'],
            ['name' => 'Jaipur Metro Phase 2', 'company_id' => $company->id, 'status' => 'active']
        );

        $member = Member::firstOrCreate(
            ['email' => 'engineer_test@cadmax.com'],
            ['name' => 'Vikram Singh', 'username' => 'engineer_test', 'slug' => 'vikram-singh', 'password' => bcrypt('password123'), 'status' => 1, 'created_by' => $owner->id]
        );

        $attendance = AttendanceRecord::updateOrCreate(
            [
                'project_id' => $project->id,
                'member_id' => $member->id,
                'attendance_date' => Carbon::today()->toDateString(),
            ],
            [
                'check_in_at' => Carbon::today()->setTime(9, 15, 0),
                'check_out_at' => Carbon::today()->setTime(18, 0, 0),
                'check_in_latitude' => 26.9124000,
                'check_in_longitude' => 75.7873000,
                'check_out_latitude' => 26.9150000,
                'check_out_longitude' => 75.7900000,
                'gps_accuracy_meters' => 10.5,
                'status' => 'pending',
                'notes' => 'On-site foundation inspection completed',
            ]
        );

        $response = $this->actingAs($owner, 'superadmin')
            ->getJson("/super/construction/attendance/{$attendance->id}");

        $response->assertOk();
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertEquals($attendance->id, $data['data']['id']);
        $this->assertEquals('Vikram Singh', $data['data']['employee']['name']);
        $this->assertEquals('8h 45m', $data['data']['duration_formatted']);
        $this->assertEquals(26.9124, $data['data']['check_in']['latitude']);
        $this->assertEquals(75.7873, $data['data']['check_in']['longitude']);
        $this->assertEquals(26.915, $data['data']['check_out']['latitude']);
        $this->assertEquals(75.79, $data['data']['check_out']['longitude']);
        $this->assertNotNull($data['data']['distance_meters']);
    }

    public function test_attendance_review_endpoint_updates_status(): void
    {
        $owner = SuperAdmin::firstOrCreate(
            ['email' => 'projectowner_test@cadmax.com'],
            ['name' => 'Project Owner Test', 'password' => bcrypt('password123'), 'status' => 1]
        );
        $owner->company_id = null;
        $owner->save();

        $company = Company::firstOrCreate(['name' => 'Jaipur Metro Infra']);
        $project = Project::firstOrCreate(
            ['project_code' => 'PRJ-JPR-11'],
            ['name' => 'Jaipur Station Pier 5', 'company_id' => $company->id, 'status' => 'active']
        );

        $member = Member::firstOrCreate(
            ['email' => 'worker_test@cadmax.com'],
            ['name' => 'Rahul Sharma', 'username' => 'worker_test', 'slug' => 'rahul-sharma', 'password' => bcrypt('password123'), 'status' => 1, 'created_by' => $owner->id]
        );

        $attendance = AttendanceRecord::updateOrCreate(
            [
                'project_id' => $project->id,
                'member_id' => $member->id,
                'attendance_date' => Carbon::today()->toDateString(),
            ],
            [
                'check_in_at' => Carbon::today()->setTime(8, 30, 0),
                'status' => 'pending',
            ]
        );

        $response = $this->actingAs($owner, 'superadmin')
            ->post("/super/construction/attendance/{$attendance->id}/review", [
                'status' => 'approved',
                'review_notes' => 'Verified on site via GPS and biometric.',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('construction_attendance_records', [
            'id' => $attendance->id,
            'status' => 'approved',
            'review_notes' => 'Verified on site via GPS and biometric.',
        ]);
    }

    public function test_company_superadmin_cannot_access_other_company_attendance_detail(): void
    {
        $companyA = Company::firstOrCreate(['name' => 'Company A Restricted']);
        $companyB = Company::firstOrCreate(['name' => 'Company B Restricted']);

        $superAdminA = SuperAdmin::firstOrCreate(
            ['email' => 'super_a@cadmax.com'],
            ['name' => 'Super Admin A', 'password' => bcrypt('password123'), 'status' => 1]
        );
        $superAdminA->company_id = $companyA->id;
        $superAdminA->save();

        $projectB = Project::firstOrCreate(
            ['project_code' => 'PRJ-RESTRICTED-B'],
            ['name' => 'Project B Site', 'company_id' => $companyB->id, 'status' => 'active']
        );

        $memberB = Member::firstOrCreate(
            ['email' => 'member_b@cadmax.com'],
            ['name' => 'Member B', 'username' => 'member_b', 'slug' => 'member-b', 'password' => bcrypt('password123'), 'status' => 1, 'created_by' => $superAdminA->id]
        );

        $attendanceB = AttendanceRecord::updateOrCreate(
            [
                'project_id' => $projectB->id,
                'member_id' => $memberB->id,
                'attendance_date' => Carbon::today()->toDateString(),
            ],
            [
                'check_in_at' => Carbon::today()->setTime(9, 0, 0),
                'status' => 'pending',
            ]
        );

        // SuperAdmin A attempts to view Attendance of Project B
        $response = $this->actingAs($superAdminA, 'superadmin')
            ->getJson("/super/construction/attendance/{$attendanceB->id}");

        $response->assertForbidden();
    }
}
