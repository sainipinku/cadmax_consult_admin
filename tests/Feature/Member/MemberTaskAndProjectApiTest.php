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

    public function test_member_projects_api_filters_strictly_by_passed_date_params(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Date Scoped Co', 'status' => 'active']);
        $member = $this->createMember($admin, 'Rohit Verma', $company->id);

        $targetDate = Carbon::parse('2026-11-20')->setTimezone('Asia/Kolkata')->startOfDay();

        // Project 1: Spans 2026-11-20 (Nov 15 - Nov 30)
        $project1 = $this->createProject(
            'Active Expressway Nov 20',
            $company,
            Carbon::parse('2026-11-15')->setTimezone('Asia/Kolkata')->startOfDay(),
            Carbon::parse('2026-11-30')->setTimezone('Asia/Kolkata')->startOfDay()
        );
        ProjectTeamMember::create([
            'project_id' => $project1->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        // Project 2: Ended earlier (Nov 01 - Nov 10) - MUST NOT match Nov 20
        $project2 = $this->createProject(
            'Past Overpass Ended Nov 10',
            $company,
            Carbon::parse('2026-11-01')->setTimezone('Asia/Kolkata')->startOfDay(),
            Carbon::parse('2026-11-10')->setTimezone('Asia/Kolkata')->startOfDay()
        );
        ProjectTeamMember::create([
            'project_id' => $project2->id,
            'member_id' => $member->id,
            'is_primary' => false,
            'status' => 'active',
        ]);

        // Project 3: Starts later (Nov 25 - Dec 15) - MUST NOT match Nov 20
        $project3 = $this->createProject(
            'Future Tunnel Starts Nov 25',
            $company,
            Carbon::parse('2026-11-25')->setTimezone('Asia/Kolkata')->startOfDay(),
            Carbon::parse('2026-12-15')->setTimezone('Asia/Kolkata')->startOfDay()
        );
        ProjectTeamMember::create([
            'project_id' => $project3->id,
            'member_id' => $member->id,
            'is_primary' => false,
            'status' => 'active',
        ]);

        // 1. Query with ?date=2026-11-20 (YYYY-MM-DD)
        $respYmd = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?date=2026-11-20');
        $respYmd->assertOk();
        $namesYmd = collect($respYmd->json('data'))->pluck('name')->all();
        $this->assertContains('Active Expressway Nov 20', $namesYmd);
        $this->assertNotContains('Past Overpass Ended Nov 10', $namesYmd);
        $this->assertNotContains('Future Tunnel Starts Nov 25', $namesYmd);
        $this->assertCount(1, $namesYmd);
        $this->assertEquals('2026-11-20', $respYmd->json('date'));
        $this->assertEquals('20/11/2026', $respYmd->json('date_formatted'));
        $this->assertEquals('2026-11-20', $respYmd->json('filter_date'));

        // 2. Query with ?date=20/11/2026 (DD/MM/YYYY)
        $respDmySlash = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?date=20/11/2026');
        $respDmySlash->assertOk();
        $namesDmySlash = collect($respDmySlash->json('data'))->pluck('name')->all();
        $this->assertCount(1, $namesDmySlash);
        $this->assertEquals('Active Expressway Nov 20', $namesDmySlash[0]);

        // 3. Query with ?date=20-11-2026 (DD-MM-YYYY)
        $respDmyDash = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?date=20-11-2026');
        $respDmyDash->assertOk();
        $namesDmyDash = collect($respDmyDash->json('data'))->pluck('name')->all();
        $this->assertCount(1, $namesDmyDash);

        // 4. Query with ?assigned_date=2026-11-20
        $respAssigned = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?assigned_date=2026-11-20');
        $respAssigned->assertOk();
        $namesAssigned = collect($respAssigned->json('data'))->pluck('name')->all();
        $this->assertCount(1, $namesAssigned);

        // 5. Query with ?target_date=2026-11-20
        $respTarget = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?target_date=2026-11-20');
        $respTarget->assertOk();
        $namesTarget = collect($respTarget->json('data'))->pluck('name')->all();
        $this->assertCount(1, $namesTarget);

        // 6. Query with ?date=2026-11-25 -> Project 1 (Nov 15-30) and Project 3 (Nov 25-Dec 15) match, Project 2 does NOT
        $resp25 = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?date=2026-11-25');
        $resp25->assertOk();
        $names25 = collect($resp25->json('data'))->pluck('name')->all();
        $this->assertContains('Active Expressway Nov 20', $names25);
        $this->assertContains('Future Tunnel Starts Nov 25', $names25);
        $this->assertNotContains('Past Overpass Ended Nov 10', $names25);
        $this->assertCount(2, $names25);

        // 7. Query without date parameter -> Returns all 3 projects
        $respAll = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects');
        $respAll->assertOk();
        $this->assertEquals(3, $respAll->json('pagination.total'));
        $this->assertNull($respAll->json('date'));

        // 8. Query /api/member/dashboard/projects?date=2026-11-20 (dashboard route alias)
        $respDashAlias = $this->actingAs($member, 'sanctum')->getJson('/api/member/dashboard/projects?date=2026-11-20');
        $respDashAlias->assertOk();
        $this->assertCount(1, $respDashAlias->json('data'));
        $this->assertEquals('2026-11-20', $respDashAlias->json('date'));
    }

    public function test_member_projects_api_filters_accurately_by_status_param(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Status Filter Co', 'status' => 'active']);
        $member = $this->createMember($admin, 'Amit Kumar', $company->id);

        $today = now()->setTimezone('Asia/Kolkata')->startOfDay();

        // 1. Running / Active project
        $activeProject = $this->createProject('Metro Line 3 Running', $company, $today->copy()->subDays(5), $today->copy()->addDays(20));
        $activeProject->update(['status' => 'active']);
        ProjectTeamMember::create([
            'project_id' => $activeProject->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        // 2. Completed project
        $completedProject = $this->createProject('City Stadium Completed', $company, $today->copy()->subDays(30), $today->copy()->subDays(5));
        $completedProject->update(['status' => 'completed']);
        ProjectTeamMember::create([
            'project_id' => $completedProject->id,
            'member_id' => $member->id,
            'is_primary' => false,
            'status' => 'active',
        ]);

        // 3. Draft / Planning project
        $planningProject = $this->createProject('Airport Terminal Planning', $company, $today->copy()->addDays(10), $today->copy()->addDays(60));
        $planningProject->update(['status' => 'draft']);
        ProjectTeamMember::create([
            'project_id' => $planningProject->id,
            'member_id' => $member->id,
            'is_primary' => false,
            'status' => 'active',
        ]);

        // 4. On Hold project
        $onHoldProject = $this->createProject('Riverfront Park On Hold', $company, $today->copy()->subDays(15), $today->copy()->addDays(15));
        $onHoldProject->update(['status' => 'on_hold']);
        ProjectTeamMember::create([
            'project_id' => $onHoldProject->id,
            'member_id' => $member->id,
            'is_primary' => false,
            'status' => 'active',
        ]);

        // Test ?status=running matches active/running project
        $respRunning = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?status=running');
        $respRunning->assertOk();
        $runningNames = collect($respRunning->json('data'))->pluck('name')->all();
        $this->assertContains('Metro Line 3 Running', $runningNames);
        $this->assertNotContains('City Stadium Completed', $runningNames);
        $this->assertNotContains('Airport Terminal Planning', $runningNames);
        $this->assertNotContains('Riverfront Park On Hold', $runningNames);
        $this->assertCount(1, $runningNames);

        // Test ?status=active matches active/running project
        $respActive = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?status=active');
        $respActive->assertOk();
        $activeNames = collect($respActive->json('data'))->pluck('name')->all();
        $this->assertContains('Metro Line 3 Running', $activeNames);
        $this->assertCount(1, $activeNames);

        // Test ?status=completed matches completed project
        $respCompleted = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?status=completed');
        $respCompleted->assertOk();
        $completedNames = collect($respCompleted->json('data'))->pluck('name')->all();
        $this->assertContains('City Stadium Completed', $completedNames);
        $this->assertNotContains('Metro Line 3 Running', $completedNames);
        $this->assertCount(1, $completedNames);

        // Test ?status=planning matches draft/planning project
        $respPlanning = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?status=planning');
        $respPlanning->assertOk();
        $planningNames = collect($respPlanning->json('data'))->pluck('name')->all();
        $this->assertContains('Airport Terminal Planning', $planningNames);
        $this->assertNotContains('Metro Line 3 Running', $planningNames);
        $this->assertCount(1, $planningNames);

        // Test ?status=draft matches draft/planning project
        $respDraft = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?status=draft');
        $respDraft->assertOk();
        $draftNames = collect($respDraft->json('data'))->pluck('name')->all();
        $this->assertContains('Airport Terminal Planning', $draftNames);
        $this->assertCount(1, $draftNames);

        // Test ?status=on_hold matches on_hold project
        $respOnHold = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?status=on_hold');
        $respOnHold->assertOk();
        $onHoldNames = collect($respOnHold->json('data'))->pluck('name')->all();
        $this->assertContains('Riverfront Park On Hold', $onHoldNames);
        $this->assertCount(1, $onHoldNames);

        // Test ?status=all returns all 4 projects
        $respAll = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?status=all');
        $respAll->assertOk();
        $this->assertCount(4, $respAll->json('data'));

        // Test alias route /api/member/dashboard/projects?status=completed
        $respDashCompleted = $this->actingAs($member, 'sanctum')->getJson('/api/member/dashboard/projects?status=completed');
        $respDashCompleted->assertOk();
        $dashCompletedNames = collect($respDashCompleted->json('data'))->pluck('name')->all();
        $this->assertContains('City Stadium Completed', $dashCompletedNames);
        $this->assertCount(1, $dashCompletedNames);

        // Test combined status + date filtering
        $respCombined = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?status=running&date=' . $today->toDateString());
        $respCombined->assertOk();
        $combinedNames = collect($respCombined->json('data'))->pluck('name')->all();
        $this->assertContains('Metro Line 3 Running', $combinedNames);
        $this->assertCount(1, $combinedNames);

        // 5. Test DB synonym support: Project stored in DB with status='running' (synonym of active)
        $synonymRunningProject = $this->createProject('Flyover Stored As Running', $company, $today->copy()->subDays(5), $today->copy()->addDays(20));
        $synonymRunningProject->update(['status' => 'running']);
        ProjectTeamMember::create([
            'project_id' => $synonymRunningProject->id,
            'member_id' => $member->id,
            'is_primary' => false,
            'status' => 'active',
        ]);

        // When querying ?status=running, BOTH 'active' and 'running' DB projects must match
        $respRunningBoth = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?status=running');
        $respRunningBoth->assertOk();
        $runningBothNames = collect($respRunningBoth->json('data'))->pluck('name')->all();
        $this->assertContains('Metro Line 3 Running', $runningBothNames);
        $this->assertContains('Flyover Stored As Running', $runningBothNames);
        $this->assertCount(2, $runningBothNames);

        // When querying ?status=active, BOTH 'active' and 'running' DB projects must match
        $respActiveBoth = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?status=active');
        $respActiveBoth->assertOk();
        $activeBothNames = collect($respActiveBoth->json('data'))->pluck('name')->all();
        $this->assertContains('Metro Line 3 Running', $activeBothNames);
        $this->assertContains('Flyover Stored As Running', $activeBothNames);
        $this->assertCount(2, $activeBothNames);

        // 6. Test comma-separated multi-status filtering (?status=running,completed)
        $respMulti = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?status=running,completed');
        $respMulti->assertOk();
        $multiNames = collect($respMulti->json('data'))->pluck('name')->all();
        $this->assertContains('Metro Line 3 Running', $multiNames);
        $this->assertContains('Flyover Stored As Running', $multiNames);
        $this->assertContains('City Stadium Completed', $multiNames);
        $this->assertNotContains('Airport Terminal Planning', $multiNames);
        $this->assertCount(3, $multiNames);

        // 7. Test project_status alias (?project_status=completed)
        $respAlias = $this->actingAs($member, 'sanctum')->getJson('/api/member/projects?project_status=completed');
        $respAlias->assertOk();
        $aliasNames = collect($respAlias->json('data'))->pluck('name')->all();
        $this->assertContains('City Stadium Completed', $aliasNames);
        $this->assertCount(1, $aliasNames);
    }
}
