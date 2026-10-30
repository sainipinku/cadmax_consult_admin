<?php

namespace Tests\Feature\Member;

use App\Models\Client;
use App\Models\Company;
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

class MemberDashboardTodayAssignedTasksTest extends TestCase
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

    private function createProject(string $name, Company $company): Project
    {
        $client = Client::create([
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
            'start_date' => now()->subDays(10),
            'expected_end_date' => now()->addDays(20),
            'project_address' => 'Test Location',
            'progress_percent' => 20,
        ]);
    }

    public function test_member_dashboard_api_returns_only_today_assigned_tasks_and_today_summary(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Apex Infra', 'status' => 'active']);
        $member = $this->createMember($admin, 'Rohan Verma', $company->id);
        $project = $this->createProject('Metro Station Site', $company);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $today = now()->setTimezone('Asia/Kolkata')->startOfDay();
        $yesterday = $today->copy()->subDays(2);
        $nextWeek = $today->copy()->addDays(7);

        // 1. Task Assigned FOR TODAY (Scheduled today)
        $todayTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Today Excavation Task',
            'project_id' => $project->id,
            'status' => 'in_progress',
            'priority' => 'high',
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

        // 2. Task Assigned spanning multiple days including today (e.g. yesterday to tomorrow)
        $spanningTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Multi-day Foundation Work',
            'project_id' => $project->id,
            'status' => 'planned',
            'priority' => 'medium',
            'start_date' => $today->copy()->subDay(),
            'end_date' => $today->copy()->addDay(),
            'created_by' => $member->id,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $spanningTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        // 3. Past Task (Scheduled in the past, ended yesterday) - MUST NOT appear in dashboard tasks
        $pastTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Past Soil Testing Task',
            'project_id' => $project->id,
            'status' => 'planned',
            'priority' => 'low',
            'start_date' => $yesterday->copy()->subDays(3),
            'end_date' => $yesterday->copy(),
            'created_by' => $member->id,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $pastTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        // 4. Future Task (Starts next week) - MUST NOT appear in dashboard tasks
        $futureTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Future Girder Erection',
            'project_id' => $project->id,
            'status' => 'planned',
            'priority' => 'critical',
            'start_date' => $nextWeek->copy(),
            'end_date' => $nextWeek->copy()->addDays(4),
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

        // Hit GET /api/member/dashboard
        $response = $this->actingAs($member, 'sanctum')->getJson('/api/member/dashboard');
        $response->assertOk();

        $data = $response->json();
        $this->assertTrue($data['success']);

        // Verify tasks in response: ONLY Today tasks must be present
        $tasksInDashboard = collect($data['tasks'])->pluck('title')->all();
        $this->assertContains('Today Excavation Task', $tasksInDashboard);
        $this->assertContains('Multi-day Foundation Work', $tasksInDashboard);
        $this->assertNotContains('Past Soil Testing Task', $tasksInDashboard);
        $this->assertNotContains('Future Girder Erection', $tasksInDashboard);
        $this->assertCount(2, $tasksInDashboard);

        // Verify todays_summary reflects ONLY today's assigned tasks
        $this->assertEquals(2, $data['todays_summary']['pending_tasks']); // 1 in_progress + 1 planned for today
        $this->assertEquals(0, $data['todays_summary']['completed_tasks']);

        // Verify todays_tasks also has the exact same 2 tasks
        $todaysTasks = collect($data['todays_tasks'])->pluck('title')->all();
        $this->assertEquals($tasksInDashboard, $todaysTasks);
    }

    public function test_dateless_tasks_created_past_are_excluded_and_dateless_tasks_assigned_today_are_included(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Metro Infra', 'status' => 'active']);
        $member = $this->createMember($admin, 'Amit Kumar', $company->id);
        $project = $this->createProject('Depot Line 3', $company);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $pastDate = now()->setTimezone('Asia/Kolkata')->subDays(10);

        // 1. Task created in the past without dates and assignment created in past -> MUST NOT be in today tasks
        $oldDatelessTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Old Dateless Cleanup Task',
            'project_id' => $project->id,
            'status' => 'planned',
            'priority' => 'low',
            'created_by' => $member->id,
        ]);
        $oldDatelessTask->timestamps = false;
        $oldDatelessTask->created_at = $pastDate;
        $oldDatelessTask->saveQuietly();

        $oldAssignment = TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $oldDatelessTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);
        $oldAssignment->timestamps = false;
        $oldAssignment->created_at = $pastDate;
        $oldAssignment->saveQuietly();

        // 2. Task created today without dates -> MUST be in today tasks
        $todayDatelessTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Immediate Today Action',
            'project_id' => $project->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'created_by' => $member->id,
            'created_at' => now(),
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $todayDatelessTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/member/dashboard');
        $response->assertOk();

        $titles = collect($response->json('tasks'))->pluck('title')->all();
        $this->assertContains('Immediate Today Action', $titles);
        $this->assertNotContains('Old Dateless Cleanup Task', $titles);
        $this->assertCount(1, $titles);
    }

    public function test_legacy_execution_tasks_filter_strictly_by_today_in_dashboard(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Legacy Infra', 'status' => 'active']);
        $member = $this->createMember($admin, 'Suresh Patel', $company->id);
        $project = $this->createProject('Flyover Project', $company);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $today = now()->setTimezone('Asia/Kolkata')->startOfDay();
        $yesterday = $today->copy()->subDays(2);
        $nextMonth = $today->copy()->addDays(30);

        $plan = \App\Models\ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'PLN-001',
            'title' => 'Master Execution Plan',
            'status' => 'approved',
        ]);

        // 1. Legacy ExecutionTask for today
        $todayLegacy = ExecutionTask::create([
            'task_code' => 'TSK-LEG-001',
            'title' => 'Today Piling Work',
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'planned_start_date' => $today->copy(),
            'planned_end_date' => $today->copy(),
        ]);
        ExecutionTaskAssignee::create([
            'project_id' => $project->id,
            'execution_task_id' => $todayLegacy->id,
            'member_id' => $member->id,
            'status' => 'active',
            'assigned_by_type' => 'super_admin',
            'assigned_by_id' => $admin->id,
        ]);

        // 2. Legacy ExecutionTask in the past
        $pastLegacy = ExecutionTask::create([
            'task_code' => 'TSK-LEG-002',
            'title' => 'Past Soil Survey',
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'status' => 'planned',
            'priority' => 'low',
            'planned_start_date' => $yesterday->copy()->subDays(5),
            'planned_end_date' => $yesterday->copy(),
        ]);
        $pastLegacy->timestamps = false;
        $pastLegacy->created_at = $yesterday;
        $pastLegacy->saveQuietly();

        $pastLegacyAssignee = ExecutionTaskAssignee::create([
            'project_id' => $project->id,
            'execution_task_id' => $pastLegacy->id,
            'member_id' => $member->id,
            'status' => 'active',
            'assigned_by_type' => 'super_admin',
            'assigned_by_id' => $admin->id,
        ]);
        $pastLegacyAssignee->timestamps = false;
        $pastLegacyAssignee->created_at = $yesterday;
        $pastLegacyAssignee->saveQuietly();

        // 3. Legacy ExecutionTask in the future
        $futureLegacy = ExecutionTask::create([
            'task_code' => 'TSK-LEG-003',
            'title' => 'Future Pier Construction',
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'status' => 'planned',
            'priority' => 'medium',
            'planned_start_date' => $nextMonth->copy(),
            'planned_end_date' => $nextMonth->copy()->addDays(5),
        ]);
        ExecutionTaskAssignee::create([
            'project_id' => $project->id,
            'execution_task_id' => $futureLegacy->id,
            'member_id' => $member->id,
            'status' => 'active',
            'assigned_by_type' => 'super_admin',
            'assigned_by_id' => $admin->id,
        ]);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/member/dashboard');
        $response->assertOk();

        $titles = collect($response->json('tasks'))->pluck('title')->all();
        $this->assertContains('Today Piling Work', $titles);
        $this->assertNotContains('Past Soil Survey', $titles);
        $this->assertNotContains('Future Pier Construction', $titles);
        $this->assertCount(1, $titles);

        // Also test GET /api/member/todays-tasks
        $todaysResponse = $this->actingAs($member, 'sanctum')->getJson('/api/member/todays-tasks');
        $todaysResponse->assertOk();
        $todaysTitles = collect($todaysResponse->json('todays_tasks'))->pluck('title')->all();
        $this->assertContains('Today Piling Work', $todaysTitles);
        $this->assertNotContains('Past Soil Survey', $todaysTitles);
        $this->assertNotContains('Future Pier Construction', $todaysTitles);
        $this->assertCount(1, $todaysTitles);
    }

    public function test_root_tasks_api_supports_today_filter(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Root Tasks Co', 'status' => 'active']);
        $member = $this->createMember($admin, 'Vikas Singh', $company->id);
        $project = $this->createProject('Tower Construction', $company);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $today = now()->setTimezone('Asia/Kolkata')->startOfDay();
        $tomorrow = $today->copy()->addDay();

        $todayTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Today Tower Welding',
            'project_id' => $project->id,
            'status' => 'in_progress',
            'priority' => 'high',
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

        $tomorrowTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Tomorrow Glass Fitting',
            'project_id' => $project->id,
            'status' => 'planned',
            'priority' => 'medium',
            'start_date' => $tomorrow->copy(),
            'end_date' => $tomorrow->copy(),
            'created_by' => $member->id,
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

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/tasks?today=true');
        $response->assertOk();

        $titles = collect($response->json('tasks.data'))->pluck('title')->all();
        $this->assertContains('Today Tower Welding', $titles);
        $this->assertNotContains('Tomorrow Glass Fitting', $titles);
        $this->assertCount(1, $titles);
    }

    public function test_member_tasks_api_filters_accurately_by_passed_date_params(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Date Filter Co', 'status' => 'active']);
        $member = $this->createMember($admin, 'Deepak Sharma', $company->id);
        $project = $this->createProject('Expressway Sector 4', $company);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $baseDate = Carbon::parse('2026-11-15')->setTimezone('Asia/Kolkata')->startOfDay();
        $targetDate = Carbon::parse('2026-11-20')->setTimezone('Asia/Kolkata')->startOfDay();
        $otherDate = Carbon::parse('2026-11-25')->setTimezone('Asia/Kolkata')->startOfDay();

        // Task 1: Scheduled for 2026-11-20 (exact match)
        $targetTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Electrical Cabling On 20th',
            'project_id' => $project->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'start_date' => $targetDate->copy(),
            'end_date' => $targetDate->copy(),
            'created_by' => $member->id,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $targetTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        // Task 2: Scheduled spanning across 2026-11-20 (2026-11-18 to 2026-11-22)
        $spanningTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Road Asphalting Week',
            'project_id' => $project->id,
            'status' => 'planned',
            'priority' => 'critical',
            'start_date' => $targetDate->copy()->subDays(2),
            'end_date' => $targetDate->copy()->addDays(2),
            'created_by' => $member->id,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $spanningTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        // Task 3: Scheduled earlier (2026-11-15) - MUST NOT match 2026-11-20
        $pastTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Soil Grading On 15th',
            'project_id' => $project->id,
            'status' => 'completed',
            'priority' => 'medium',
            'start_date' => $baseDate->copy(),
            'end_date' => $baseDate->copy(),
            'created_by' => $member->id,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $pastTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        // Task 4: Scheduled later (2026-11-25) - MUST NOT match 2026-11-20
        $futureTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Guard Rail Installation On 25th',
            'project_id' => $project->id,
            'status' => 'planned',
            'priority' => 'low',
            'start_date' => $otherDate->copy(),
            'end_date' => $otherDate->copy(),
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

        // 1. Query with ?date=2026-11-20 (YYYY-MM-DD)
        $respYmd = $this->actingAs($member, 'sanctum')->getJson('/api/member/tasks?date=2026-11-20');
        $respYmd->assertOk();
        $titlesYmd = collect($respYmd->json('data'))->pluck('title')->all();
        $this->assertContains('Electrical Cabling On 20th', $titlesYmd);
        $this->assertContains('Road Asphalting Week', $titlesYmd);
        $this->assertNotContains('Soil Grading On 15th', $titlesYmd);
        $this->assertNotContains('Guard Rail Installation On 25th', $titlesYmd);
        $this->assertCount(2, $titlesYmd);
        $this->assertEquals('2026-11-20', $respYmd->json('date'));

        // 2. Query with ?date=20/11/2026 (DD/MM/YYYY)
        $respDmySlash = $this->actingAs($member, 'sanctum')->getJson('/api/member/tasks?date=20/11/2026');
        $respDmySlash->assertOk();
        $titlesDmySlash = collect($respDmySlash->json('data'))->pluck('title')->all();
        $this->assertCount(2, $titlesDmySlash);

        // 3. Query with ?date=20-11-2026 (DD-MM-YYYY)
        $respDmyDash = $this->actingAs($member, 'sanctum')->getJson('/api/member/tasks?date=20-11-2026');
        $respDmyDash->assertOk();
        $titlesDmyDash = collect($respDmyDash->json('data'))->pluck('title')->all();
        $this->assertCount(2, $titlesDmyDash);

        // 4. Query with ?assigned_date=2026-11-20
        $respAssignedDate = $this->actingAs($member, 'sanctum')->getJson('/api/member/tasks?assigned_date=2026-11-20');
        $respAssignedDate->assertOk();
        $titlesAssigned = collect($respAssignedDate->json('data'))->pluck('title')->all();
        $this->assertCount(2, $titlesAssigned);

        // 5. Query with ?date=2026-11-25 -> Only Task 4 matches
        $resp25 = $this->actingAs($member, 'sanctum')->getJson('/api/member/tasks?date=2026-11-25');
        $resp25->assertOk();
        $titles25 = collect($resp25->json('data'))->pluck('title')->all();
        $this->assertCount(1, $titles25);
        $this->assertEquals('Guard Rail Installation On 25th', $titles25[0]);

        // 6. Query without date parameter -> Returns all 4 tasks
        $respAll = $this->actingAs($member, 'sanctum')->getJson('/api/member/tasks');
        $respAll->assertOk();
        $this->assertEquals(4, $respAll->json('pagination.total'));
        $this->assertNull($respAll->json('date'));

        // 7. Query with ?target_date=2026-11-20 and ?task_date=2026-11-20
        $respTargetDate = $this->actingAs($member, 'sanctum')->getJson('/api/member/tasks?target_date=2026-11-20');
        $respTargetDate->assertOk();
        $this->assertCount(2, $respTargetDate->json('data'));
        $this->assertEquals('2026-11-20', $respTargetDate->json('date'));
        $this->assertEquals('20/11/2026', $respTargetDate->json('date_formatted'));

        $respTaskDate = $this->actingAs($member, 'sanctum')->getJson('/api/member/tasks?task_date=2026-11-20');
        $respTaskDate->assertOk();
        $this->assertCount(2, $respTaskDate->json('data'));

        // 8. Test /api/member/todays-tasks?date=2026-11-20
        $respTodaysApi = $this->actingAs($member, 'sanctum')->getJson('/api/member/todays-tasks?date=2026-11-20');
        $respTodaysApi->assertOk();
        $todaysTitles = collect($respTodaysApi->json('data'))->pluck('title')->all();
        $this->assertContains('Electrical Cabling On 20th', $todaysTitles);
        $this->assertContains('Road Asphalting Week', $todaysTitles);
        $this->assertNotContains('Soil Grading On 15th', $todaysTitles);
        $this->assertNotContains('Guard Rail Installation On 25th', $todaysTitles);

        // 9. Test /api/tasks?date=2026-11-20
        $respRootTasksDate = $this->actingAs($member, 'sanctum')->getJson('/api/tasks?date=2026-11-20');
        $respRootTasksDate->assertOk();
        $rootTitles = collect($respRootTasksDate->json('tasks.data'))->pluck('title')->all();
        $this->assertContains('Electrical Cabling On 20th', $rootTitles);
        $this->assertContains('Road Asphalting Week', $rootTitles);
        $this->assertNotContains('Soil Grading On 15th', $rootTitles);
        $this->assertNotContains('Guard Rail Installation On 25th', $rootTitles);
    }
}
