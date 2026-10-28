<?php

namespace Tests\Feature\Member;

use App\Models\Company;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberTaskAndProjectApiTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
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

    private function createProject(string $name, Company $company, ?Carbon $startDate = null, ?Carbon $endDate = null): Project
    {
        $client = \App\Models\Client::create([
            'company_id' => $company->id,
            'client_code' => 'CLI-' . strtoupper(Str::random(4)),
            'name' => $name . ' Real Client',
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . strtoupper(Str::random(4)),
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'name' => $name,
            'status' => 'active',
            'start_date' => $startDate ?? now()->subDays(5),
            'expected_end_date' => $endDate ?? now()->addDays(20),
            'project_address' => 'Real Site Location, Jaipur',
            'progress_percent' => 35,
        ]);
    }

    public function test_tasks_api_filters_by_date_and_returns_complete_accurate_data(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Test Company', 'status' => 'active']);
        $this->createMember($admin, 'Owner 1', $company->id);
        $this->createMember($admin, 'Owner 2', $company->id);

        $member = $this->createMember($admin, 'Active Worker', $company->id);
        $project = $this->createProject('Metro Project', $company);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $today = now()->setTimezone('Asia/Kolkata')->startOfDay();
        $tomorrow = $today->copy()->addDay();

        // Task 1: Scheduled for today
        $todayTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Pillar Drilling Today',
            'description' => 'Drill 4 pillars at gate 2',
            'project_id' => $project->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'start_date' => $today->copy(),
            'end_date' => $today->copy(),
            'created_by' => $member->id,
            'progress_percent' => 50,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $todayTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        // Task 2: Scheduled for tomorrow
        $tomorrowTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Concrete Pouring Tomorrow',
            'description' => 'Pour concrete for foundation',
            'project_id' => $project->id,
            'status' => 'planned',
            'priority' => 'critical',
            'start_date' => $tomorrow->copy(),
            'end_date' => $tomorrow->copy()->addDays(2),
            'created_by' => $member->id,
            'progress_percent' => 0,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $tomorrowTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        // Request without filter -> Returns both
        $responseAll = $this->actingAs($member, 'sanctum')->getJson('/api/member/tasks');
        $responseAll->assertOk();
        $this->assertEquals(2, $responseAll->json('pagination.total'));

        // Request with date=today filter
        $responseToday = $this->actingAs($member, 'sanctum')->getJson('/api/member/tasks?date=' . $today->toDateString());
        $responseToday->assertOk();
        $dataToday = $responseToday->json('data');
        $this->assertCount(1, $dataToday);
        $this->assertEquals('Pillar Drilling Today', $dataToday[0]['title']);
        $this->assertEquals(50, $dataToday[0]['progress_percent']);
        $this->assertEquals('Drill 4 pillars at gate 2', $dataToday[0]['description']);
        $this->assertEquals('high', $dataToday[0]['priority']);
        $this->assertEquals($today->toDateString(), $dataToday[0]['start_date']);

        // Request with filter=today
        $responseFilterToday = $this->actingAs($member, 'sanctum')->getJson('/api/member/tasks?filter=today');
        $responseFilterToday->assertOk();
        $this->assertCount(1, $responseFilterToday->json('data'));
        $this->assertEquals('Pillar Drilling Today', $responseFilterToday->json('data.0.title'));

        // Request for tomorrow's date
        $responseTomorrow = $this->actingAs($member, 'sanctum')->getJson('/api/member/tasks?date=' . $tomorrow->toDateString());
        $responseTomorrow->assertOk();
        $this->assertCount(1, $responseTomorrow->json('data'));
        $this->assertEquals('Concrete Pouring Tomorrow', $responseTomorrow->json('data.0.title'));
    }

    public function test_todays_tasks_api_does_not_include_future_tasks(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Future Task Co', 'status' => 'active']);
        $this->createMember($admin, 'Owner 1', $company->id);
        $this->createMember($admin, 'Owner 2', $company->id);

        $member = $this->createMember($admin, 'Worker Future', $company->id);
        $project = $this->createProject('Bridge Project', $company);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $today = now()->setTimezone('Asia/Kolkata')->startOfDay();
        $nextWeek = $today->copy()->addDays(7);

        // Task today
        $todayTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Bridge Survey Today',
            'project_id' => $project->id,
            'status' => 'in_progress',
            'start_date' => $today->copy(),
            'end_date' => $today->copy(),
            'created_by' => $member->id,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $todayTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        // Future task starting next week and ending next week
        $futureTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Future Girder Installation',
            'project_id' => $project->id,
            'status' => 'planned',
            'start_date' => $nextWeek->copy(),
            'end_date' => $nextWeek->copy()->addDays(3),
            'created_by' => $member->id,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $futureTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/member/todays-tasks');
        $response->assertOk();

        $titles = collect($response->json('todays_tasks'))->pluck('title')->all();
        $this->assertContains('Bridge Survey Today', $titles);
        $this->assertNotContains('Future Girder Installation', $titles);
    }

    public function test_projects_api_returns_accurate_data_and_supports_date_filter(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Real Construction Ltd', 'status' => 'active']);
        $this->createMember($admin, 'Owner 1', $company->id);
        $this->createMember($admin, 'Owner 2', $company->id);

        $member = $this->createMember($admin, 'Site Engineer', $company->id);

        $today = now()->setTimezone('Asia/Kolkata')->startOfDay();

        // Project 1: Active project with 35% progress
        $project1 = $this->createProject('Highway NH-48', $company, $today->copy()->subDays(10), $today->copy()->addDays(30));
        $project1->update([
            'project_address' => 'Jaipur-Delhi Highway KM 24',
            'progress_percent' => 35,
            'status' => 'active',
        ]);

        ProjectTeamMember::create([
            'project_id' => $project1->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        // Create 2 tasks in project1
        Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'NH-48 Task 1',
            'project_id' => $project1->id,
            'status' => 'in_progress',
            'start_date' => $today->copy(),
            'end_date' => $today->copy(),
            'created_by' => $member->id,
            'progress_percent' => 35,
        ]);
        Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'NH-48 Task 2',
            'project_id' => $project1->id,
            'status' => 'planned',
            'created_by' => $member->id,
            'progress_percent' => 35,
        ]);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects');
        $response->assertOk();

        $projectItem = collect($response->json('data'))->firstWhere('id', $project1->id);
        $this->assertNotNull($projectItem);

        // Verify accurate real data instead of fake fallbacks
        $this->assertEquals('Highway NH-48 Real Client', $projectItem['manager_name']);
        $this->assertEquals('Jaipur-Delhi Highway KM 24', $projectItem['location']);
        $this->assertEquals(35, $projectItem['progress_percent']);
        $this->assertEquals(1, $projectItem['employees_count']);
        $this->assertEquals(2, $projectItem['tasks_count']);
        $this->assertEquals('running', $projectItem['status']);

        // Date filter check: project active today is included
        $responseDate = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?date=' . $today->toDateString());
        $responseDate->assertOk();
        $this->assertNotEmpty($responseDate->json('data'));
    }

    public function test_web_routes_for_member_projects_and_tasks_redirect_properly(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Web Route Co', 'status' => 'active']);
        $this->createMember($admin, 'Owner 1', $company->id);
        $this->createMember($admin, 'Owner 2', $company->id);

        $member = $this->createMember($admin, 'Web Worker', $company->id);

        $responseTasks = $this->actingAs($member, 'web')->get('/member/tasks');
        $responseTasks->assertRedirect('/member/construction/execution');

        $responseProjects = $this->actingAs($member, 'web')->get('/member/projects');
        $responseProjects->assertRedirect('/member/construction/projects');
    }
}
