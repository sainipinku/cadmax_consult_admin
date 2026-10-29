<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberDashboardTasksTest extends TestCase
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

    public function test_member_dashboard_includes_equipment_assigned_through_employee_record(): void
    {
        $admin = $this->createSuperAdmin();
        $this->createMember($admin, 'Owner Member');
        $this->createMember($admin, 'SuperAdmin Member');

        $member = $this->createMember($admin, 'Equipment User');
        $otherMember = $this->createMember($admin, 'Other Equipment User');
        $category = EquipmentCategory::create([
            'category_name' => 'Tools',
            'status' => 1,
        ]);
        $employee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $member->id,
            'employee_id' => 'EMP-' . Str::upper(Str::random(6)),
        ]);
        $otherEmployee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $otherMember->id,
            'employee_id' => 'EMP-' . Str::upper(Str::random(6)),
        ]);

        Equipment::create([
            'equipment_id' => 'EQ-' . Str::upper(Str::random(6)),
            'category_id' => $category->id,
            'equipment_name' => 'Assigned Drill',
            'status' => 1,
            'assigned_employee_id' => $employee->id,
        ]);
        Equipment::create([
            'equipment_id' => 'EQ-' . Str::upper(Str::random(6)),
            'category_id' => $category->id,
            'equipment_name' => 'Another Member Drill',
            'status' => 1,
            'assigned_employee_id' => $otherEmployee->id,
        ]);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/member/dashboard');

        $response->assertOk();
        $response->assertJsonPath('summary.equipment_assigned_count', 1);
        $this->assertSame(
            ['Assigned Drill'],
            collect($response->json('equipment_assigned'))->pluck('equipment_name')->all()
        );
    }

    public function test_member_dashboard_returns_one_consolidated_today_task_list(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp', 'status' => 'active']);
        $project1 = $this->createProject('Site 1', $company);
        $project2 = $this->createProject('Site 2', $company);

        // Member IDs 1 and 2 are hardcoded in ConstructionAuthorizationService and Member models
        // as system owner / superadmin, so create seed members first so our test member is a regular worker.
        $this->createMember($admin, 'Owner Member', $company->id);
        $this->createMember($admin, 'SuperAdmin Member', $company->id);

        $member = $this->createMember($admin, 'John Worker', $company->id);
        $otherMember = $this->createMember($admin, 'Jane Other', $company->id);

        ProjectTeamMember::create([
            'project_id' => $project1->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);
        ProjectTeamMember::create([
            'project_id' => $project2->id,
            'member_id' => $member->id,
            'is_primary' => false,
            'status' => 'active',
        ]);

        $today = now()->setTimezone('Asia/Kolkata')->startOfDay();

        // 1. Unified task assigned to member on project 1 for today
        $unifiedTask1 = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Unified Today Task',
            'project_id' => $project1->id,
            'status' => 'in_progress',
            'start_date' => $today->copy(),
            'end_date' => $today->copy(),
            'created_by' => $member->id,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $unifiedTask1->id,
            'assigned_to' => $member->id,
            'project_id' => $project1->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        // 2. Legacy task assigned to member on project 2
        $plan = \App\Models\ExecutionPlan::create([
            'project_id' => $project2->id,
            'plan_code' => 'EXP-00001',
            'title' => 'Execution Plan',
            'status' => 'planned',
        ]);
        $legacyTask = ExecutionTask::create([
            'task_code' => 'EXT-00001',
            'title' => 'Legacy Assigned Task',
            'project_id' => $project2->id,
            'execution_plan_id' => $plan->id,
            'status' => 'planned',
            'planned_start_date' => $today->copy(),
            'planned_end_date' => $today->copy()->addDays(2),
        ]);
        ExecutionTaskAssignee::create([
            'execution_task_id' => $legacyTask->id,
            'member_id' => $member->id,
            'project_id' => $project2->id,
            'status' => 'active',
        ]);

        // 3. Task assigned only to other member (should NOT appear in member's dashboard)
        $otherTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Other Member Task',
            'project_id' => $project1->id,
            'status' => 'planned',
            'start_date' => $today->copy(),
            'end_date' => $today->copy(),
            'created_by' => $otherMember->id,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $otherTask->id,
            'assigned_to' => $otherMember->id,
            'project_id' => $project1->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/member/dashboard');

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Verify top-level keys
        $responseData = $response->json();

        $this->assertArrayNotHasKey('todays_tasks', $responseData);
        $this->assertArrayNotHasKey('today_tasks', $responseData);
        $this->assertArrayNotHasKey('today_task', $responseData);
        $this->assertArrayHasKey('tasks', $responseData);
        $this->assertArrayNotHasKey('task', $responseData);
        $this->assertArrayNotHasKey('all_tasks', $responseData);

        $todayTaskTitles = collect($responseData['tasks'])
            ->where('is_today', true)
            ->pluck('title')
            ->all();
        $this->assertContains('Unified Today Task', $todayTaskTitles);
        $this->assertContains('Legacy Assigned Task', $todayTaskTitles);
        $this->assertNotContains('Other Member Task', $todayTaskTitles);

        $taskTitles = collect($responseData['tasks'])->pluck('title')->all();
        $this->assertContains('Unified Today Task', $taskTitles);
        $this->assertContains('Legacy Assigned Task', $taskTitles);
        $this->assertNotContains('Other Member Task', $taskTitles);

        $this->assertNotEmpty($responseData['projects']);
        foreach ($responseData['projects'] as $proj) {
            $this->assertArrayNotHasKey('today_task', $proj);
            $this->assertArrayNotHasKey('today_tasks', $proj);
            $this->assertArrayNotHasKey('task', $proj);
            $this->assertArrayNotHasKey('tasks', $proj);
        }

        $this->assertNotNull($responseData['current_project']);
        $this->assertArrayNotHasKey('today_task', $responseData['current_project']);
        $this->assertArrayNotHasKey('today_tasks', $responseData['current_project']);
        $this->assertArrayNotHasKey('task', $responseData['current_project']);
        $this->assertArrayNotHasKey('tasks', $responseData['current_project']);
    }

    public function test_tasks_list_includes_recent_and_today_tasks_without_duplicates(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp 2', 'status' => 'active']);
        $project = $this->createProject('Site 20', $company);
        $member = $this->createMember($admin, 'Worker 20', $company->id);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        // Create 25 tasks assigned to this member
        for ($i = 1; $i <= 25; $i++) {
            $task = Task::create([
                'uuid' => (string) Str::uuid(),
                'title' => "Task Number {$i}",
                'project_id' => $project->id,
                'status' => 'planned',
                'created_by' => $member->id,
            ]);
            TaskAssignment::create([
                'uuid' => (string) Str::uuid(),
                'task_id' => $task->id,
                'assigned_to' => $member->id,
                'project_id' => $project->id,
                'status' => 'active',
                'assigned_by' => $admin->id,
                'assigned_by_type' => 'super_admin',
            ]);
        }

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/member/dashboard');
        $response->assertOk();

        $responseData = $response->json();
        $this->assertCount(25, $responseData['tasks']);
        $this->assertCount(25, collect($responseData['tasks'])->pluck('id')->unique());
        $this->assertCount(25, collect($responseData['tasks'])->where('is_today', true));
        $this->assertArrayNotHasKey('task', $responseData);
        $this->assertArrayNotHasKey('all_tasks', $responseData);
        $this->assertArrayNotHasKey('tasks', $responseData['projects'][0]);
        $this->assertArrayNotHasKey('task', $responseData['projects'][0]);
    }

    public function test_member_tasks_can_be_filtered_to_today_or_return_the_full_schedule(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp 3', 'status' => 'active']);
        $project = $this->createProject('Site 30', $company);
        $this->createMember($admin, 'Owner Member 30', $company->id);
        $this->createMember($admin, 'SuperAdmin Member 30', $company->id);
        $member = $this->createMember($admin, 'Worker 30', $company->id);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $today = now()->setTimezone('Asia/Kolkata')->startOfDay();
        $taskDates = [
            'Past Task' => $today->copy()->subDay(),
            'Today Task' => $today->copy(),
            'Upcoming Task' => $today->copy()->addDay(),
        ];

        foreach ($taskDates as $title => $date) {
            $task = Task::create([
                'uuid' => (string) Str::uuid(),
                'title' => $title,
                'project_id' => $project->id,
                'status' => 'planned',
                'start_date' => $date,
                'end_date' => $date,
                'created_by' => $member->id,
            ]);

            TaskAssignment::create([
                'uuid' => (string) Str::uuid(),
                'task_id' => $task->id,
                'assigned_to' => $member->id,
                'project_id' => $project->id,
                'status' => 'active',
                'assigned_by' => $admin->id,
                'assigned_by_type' => 'super_admin',
            ]);
        }

        $fullResponse = $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/tasks')
            ->assertOk()
            ->assertJsonPath('pagination.total', 3);

        $this->assertEqualsCanonicalizing(
            ['Past Task', 'Today Task', 'Upcoming Task'],
            collect($fullResponse->json('data'))->pluck('title')->all()
        );

        $todayResponse = $this->getJson('/api/member/tasks?date=today');

        $todayResponse->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Today Task');
    }
}
