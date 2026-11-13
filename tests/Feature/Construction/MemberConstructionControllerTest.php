<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberConstructionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'roles' => json_encode(['super_admin']),
            'name' => 'Construction Super Admin',
            'email' => 'super-admin@example.com',
            'phone' => '9999999999',
            'whatsapp_phone' => '9999999999',
            'password' => 'password',
        ]);
    }

    private function createMember(SuperAdmin $superAdmin, string $name): Member
    {
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(5));

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'name' => $name,
            'username' => $slug,
            'email' => $slug . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'slug' => $slug,
        ]);
    }

    private function createProject(
        string $projectCode = 'PRJ-001',
        string $slug = 'project-001',
        string $name = 'Construction Project'
    ): Project {
        $company = Company::create([
            'name' => $name . ' Company',
            'status' => 'active',
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => $projectCode . '-CLIENT',
            'name' => $name . ' Client',
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => $projectCode,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'current_stage' => 'ready_for_construction',
        ]);
    }

    private function createRole(string $slug, string $name = 'Role'): ConstructionRole
    {
        return ConstructionRole::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
            'is_system_role' => true,
            'status' => 'active',
        ]);
    }

    private function createPermission(string $slug): Permission
    {
        return Permission::create([
            'name' => $slug,
            'slug' => $slug,
            'module' => 'test',
        ]);
    }

    private function assignRoleToProject(
        Member $member,
        ConstructionRole $role,
        Project $project,
        array $permissionSlugs = []
    ): void {
        foreach ($permissionSlugs as $slug) {
            $role->permissions()->syncWithoutDetaching(
                [Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'test'])->id]
            );
        }

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $role->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    public function test_dashboard_resolves_single_project_role_context(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Dashboard Member');
        $project = $this->createProject('PRJ-DB', 'project-db', 'Dashboard Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        // dashboard.view required by the route middleware; survey.view drives the widget.
        $this->assignRoleToProject($member, $surveyor, $project, ['dashboard.view', 'survey.view']);

        $response = $this->actingAs($member, 'member')->get(route('member.construction.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Construction/Dashboard')
            ->has('active_project')
            ->has('active_role')
            ->where('active_role.slug', 'surveyor')
            ->has('permissions', 2)
        );
    }

    public function test_multiple_projects_without_selection_returns_empty_context(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Multi Project Member');
        $projectA = $this->createProject('PRJ-MPA', 'project-mpa', 'MPA');
        $projectB = $this->createProject('PRJ-MPB', 'project-mpb', 'MPB');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        $this->assignRoleToProject($member, $surveyor, $projectA, ['dashboard.view', 'survey.view']);
        $this->assignRoleToProject($member, $driver, $projectB, ['dashboard.view', 'vehicle_tracking.manage']);

        $response = $this->actingAs($member, 'member')->get(route('member.construction.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Construction/Dashboard')
            ->where('active_project', null)
            ->where('active_role', null)
            ->has('projects', 2)
        );
    }

    public function test_invalid_project_query_returns_403(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Invalid Project Member');
        $project = $this->createProject('PRJ-IPM', 'project-ipm', 'Invalid Project Member');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $this->assignRoleToProject($member, $surveyor, $project, ['dashboard.view', 'survey.view']);

        $response = $this->actingAs($member, 'member')->get(
            route('member.construction.dashboard') . '?project=999999'
        );

        $response->assertForbidden();
    }

    public function test_invalid_role_query_returns_403(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Invalid Role Member');
        $project = $this->createProject('PRJ-IRM', 'project-irm', 'Invalid Role Member');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $this->assignRoleToProject($member, $surveyor, $project, ['dashboard.view', 'survey.view']);

        $response = $this->actingAs($member, 'member')->get(
            route('member.construction.dashboard') . '?project=' . $project->id . '&role=vehicle_driver'
        );

        $response->assertForbidden();
    }

    public function test_active_role_permission_gates_attendance_check_in(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Attendance Deny');
        $project = $this->createProject('PRJ-AD', 'project-ad', 'Attendance Deny');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $this->assignRoleToProject($member, $surveyor, $project, ['dashboard.view', 'survey.view']);

        $response = $this->actingAs($member, 'member')->post(
            route('member.construction.attendance.checkin'),
            [
                'project_id' => $project->id,
                'attendance_type' => 'present',
                'attendance_date' => now()->toDateString(),
                'check_in_latitude' => 19.076,
                'check_in_longitude' => 72.877,
                'role' => 'surveyor',
            ]
        );

        $response->assertForbidden();
    }

    public function test_active_role_with_attendance_permission_can_check_in(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Attendance Allow');
        $project = $this->createProject('PRJ-AA', 'project-aa', 'Attendance Allow');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');

        $this->assignRoleToProject($member, $siteEmployee, $project, ['attendance.manage', 'attendance.mark']);

        $response = $this->actingAs($member, 'member')->post(
            route('member.construction.attendance.checkin'),
            [
                'project_id' => $project->id,
                'attendance_type' => 'present',
                'attendance_date' => now()->toDateString(),
                'check_in_latitude' => 19.076,
                'check_in_longitude' => 72.877,
                'role' => 'site_employee',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('construction_attendance_records', [
            'project_id' => $project->id,
            'member_id' => $member->id,
        ]);
    }

    public function test_execution_page_gated_by_execution_permission(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Exec Deny');
        $project = $this->createProject('PRJ-ED', 'project-ed', 'Exec Deny');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $this->assignRoleToProject($member, $surveyor, $project, ['dashboard.view', 'survey.view']);

        $response = $this->actingAs($member, 'member')->get(
            route('member.construction.execution.index') . '?project=' . $project->id . '&role=surveyor'
        );

        $response->assertForbidden();
    }

    public function test_execution_page_with_permission_and_active_project_scopes_data(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Exec Allow');
        $projectA = $this->createProject('PRJ-EA1', 'project-ea1', 'Exec A');
        $projectB = $this->createProject('PRJ-EA2', 'project-ea2', 'Exec B');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');

        $this->assignRoleToProject($member, $siteEmployee, $projectA, ['execution_task.manage', 'execution.task.view']);
        $this->assignRoleToProject($member, $siteEmployee, $projectB, ['execution_task.manage', 'execution.task.view']);

        $planA = ExecutionPlan::create([
            'project_id' => $projectA->id,
            'plan_code' => 'PLAN-A',
            'title' => 'Plan A',
            'status' => 'active',
        ]);
        $planB = ExecutionPlan::create([
            'project_id' => $projectB->id,
            'plan_code' => 'PLAN-B',
            'title' => 'Plan B',
            'status' => 'active',
        ]);

        $taskA = ExecutionTask::create([
            'project_id' => $projectA->id,
            'execution_plan_id' => $planA->id,
            'task_code' => 'TASK-A',
            'title' => 'Task A',
            'status' => 'in_progress',
        ]);
        $taskB = ExecutionTask::create([
            'project_id' => $projectB->id,
            'execution_plan_id' => $planB->id,
            'task_code' => 'TASK-B',
            'title' => 'Task B',
            'status' => 'in_progress',
        ]);

        ExecutionTaskAssignee::create([
            'project_id' => $projectA->id,
            'execution_task_id' => $taskA->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);
        ExecutionTaskAssignee::create([
            'project_id' => $projectB->id,
            'execution_task_id' => $taskB->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($member, 'member')->get(
            route('member.construction.execution.index') . '?project=' . $projectA->id . '&role=site_employee'
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Construction/Execution/Index')
            ->has('tasks', 1)
            ->where('tasks.0.id', $taskA->id)
        );
    }

    public function test_draftsman_execution_page_only_lists_draftsman_assignments(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Draftsman Member');
        $project = $this->createProject('PRJ-DRAFT', 'project-draft', 'Draft Project');
        $draftRole = $this->createRole('draft_person', 'Draft Person');
        $this->assignRoleToProject($member, $draftRole, $project, ['drafting.manage']);
        $plan = ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'PLAN-DRAFT',
            'title' => 'Drafting Plan',
            'status' => 'active',
        ]);
        $draftTask = ExecutionTask::create([
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'TASK-DRAFT',
            'title' => 'Drafting Task',
            'status' => 'in_progress',
        ]);
        $otherTask = ExecutionTask::create([
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'TASK-OTHER',
            'title' => 'Non-Drafting Task',
            'status' => 'in_progress',
        ]);
        ExecutionTaskAssignee::create([
            'project_id' => $project->id,
            'execution_task_id' => $draftTask->id,
            'member_id' => $member->id,
            'assignment_role' => 'draft_person',
            'status' => 'active',
        ]);
        ExecutionTaskAssignee::create([
            'project_id' => $project->id,
            'execution_task_id' => $otherTask->id,
            'member_id' => $member->id,
            'assignment_role' => 'worker',
            'status' => 'active',
        ]);

        $response = $this->actingAs($member, 'member')->get(
            route('member.construction.execution.index') . '?project=' . $project->id . '&role=draft_person'
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Construction/Execution/Index')
            ->has('tasks', 1)
            ->where('tasks.0.id', $draftTask->id)
        );
    }

    public function test_driver_execution_page_hides_tasks_with_draftsman_assignments(): void
    {
        $admin = $this->createSuperAdmin();
        $driver = $this->createMember($admin, 'Driver Member');
        $draftsman = $this->createMember($admin, 'Assigned Draftsman');
        $project = $this->createProject('PRJ-DRIVER', 'project-driver', 'Driver Project');
        $driverRole = $this->createRole('vehicle_driver', 'Vehicle Driver');
        $this->assignRoleToProject($driver, $driverRole, $project, ['execution.task.view']);
        $plan = ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'PLAN-DRIVER',
            'title' => 'Driver Plan',
            'status' => 'active',
        ]);
        $driverTask = ExecutionTask::create([
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'TASK-DRIVER',
            'title' => 'Driver Task',
            'status' => 'in_progress',
        ]);
        $draftTask = ExecutionTask::create([
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'TASK-DRIVER-DRAFT',
            'title' => 'Draftsman Task',
            'status' => 'in_progress',
        ]);
        foreach ([$driverTask, $draftTask] as $task) {
            ExecutionTaskAssignee::create([
                'project_id' => $project->id,
                'execution_task_id' => $task->id,
                'member_id' => $driver->id,
                'assignment_role' => 'vehicle_driver',
                'status' => 'active',
            ]);
        }
        ExecutionTaskAssignee::create([
            'project_id' => $project->id,
            'execution_task_id' => $draftTask->id,
            'member_id' => $draftsman->id,
            'assignment_role' => 'draft_person',
            'status' => 'active',
        ]);

        $response = $this->actingAs($driver, 'member')->get(
            route('member.construction.execution.index') . '?project=' . $project->id . '&role=vehicle_driver'
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Construction/Execution/Index')
            ->has('tasks', 1)
            ->where('tasks.0.id', $driverTask->id)
        );
    }

    public function test_materials_scoped_to_active_project(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Materials Member');
        $projectA = $this->createProject('PRJ-MA1', 'project-ma1', 'Materials A');
        $projectB = $this->createProject('PRJ-MA2', 'project-ma2', 'Materials B');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');

        $this->assignRoleToProject($member, $siteEmployee, $projectA, ['material_issue.manage']);
        $this->assignRoleToProject($member, $siteEmployee, $projectB, ['material_issue.manage']);

        \App\Models\Material::create([
            'project_id' => $projectA->id,
            'material_code' => 'MAT-A',
            'name' => 'Cement A',
            'unit' => 'bag',
            'status' => 'active',
        ]);
        \App\Models\Material::create([
            'project_id' => $projectB->id,
            'material_code' => 'MAT-B',
            'name' => 'Cement B',
            'unit' => 'bag',
            'status' => 'active',
        ]);

        $response = $this->actingAs($member, 'member')->get(
            route('member.construction.materials.index') . '?project=' . $projectA->id . '&role=site_employee'
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Construction/Materials/Index')
            ->has('materials', 1)
            ->where('materials.0.material_code', 'MAT-A')
        );
    }

    public function test_no_active_project_returns_empty_materials(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'No Project Materials');
        $projectA = $this->createProject('PRJ-NPM1', 'project-npm1', 'No Project A');
        $projectB = $this->createProject('PRJ-NPM2', 'project-npm2', 'No Project B');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');

        $this->assignRoleToProject($member, $siteEmployee, $projectA, ['material_issue.manage']);
        $this->assignRoleToProject($member, $siteEmployee, $projectB, ['material_issue.manage']);

        $response = $this->actingAs($member, 'member')->get(route('member.construction.materials.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Construction/Materials/Index')
            ->where('materials', [])
            ->where('stocks', [])
            ->where('myIssues', [])
        );
    }

    public function test_show_project_gates_module_data_by_permission(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Show Project Member');
        $project = $this->createProject('PRJ-SPG', 'project-spg', 'Show Project Gate');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $this->assignRoleToProject($member, $surveyor, $project, ['dashboard.view', 'survey.view']);

        $response = $this->actingAs($member, 'member')->get(
            route('member.construction.projects.show', $project->id) . '?role=surveyor'
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Member/Construction/Projects/Show')
            ->where('activityLog', [])
            ->has('project.team_members', 0)
            ->has('project.execution_tasks', 0)
            ->has('project.attendance_records', 0)
            ->has('project.daily_progress_reports', 0)
        );
    }
}