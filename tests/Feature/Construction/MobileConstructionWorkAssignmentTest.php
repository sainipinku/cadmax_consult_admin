<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionEquipment;
use App\Models\EquipmentAllocation;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\ConstructionRole;
use App\Models\SurveyPlan;
use App\Models\SurveyPlanMember;
use App\Models\SurveyVisit;
use App\Models\SurveyWorkChecklist;
use App\Models\ConstructionVehicle;
use App\Models\VehicleAssignment;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileConstructionWorkAssignmentTest extends TestCase
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

    private function createSurveyPlan(Project $project, string $code = 'SURV-001'): SurveyPlan
    {
        return SurveyPlan::create([
            'project_id' => $project->id,
            'survey_code' => $code,
            'title' => 'Survey ' . $code,
            'status' => SurveyPlan::STATUS_PLANNED,
        ]);
    }

    private function createExecutionTask(Project $project, string $code = 'TASK-001'): ExecutionTask
    {
        $plan = ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'PLAN-' . $code,
            'title' => 'Plan ' . $code,
            'status' => 'active',
        ]);

        return ExecutionTask::create([
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => $code,
            'title' => 'Task ' . $code,
            'status' => 'in_progress',
        ]);
    }

    private function createVehicle(Project $project, string $code = 'VEH-001'): ConstructionVehicle
    {
        return ConstructionVehicle::create([
            'project_id' => $project->id,
            'vehicle_code' => $code,
            'registration_number' => 'RJ14' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);
    }

    private function createEquipment(Project $project, string $code = 'EQP-001'): ConstructionEquipment
    {
        return ConstructionEquipment::create([
            'project_id' => $project->id,
            'equipment_code' => $code,
            'name' => 'Equipment ' . $code,
            'status' => 'active',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SURVEY WORK ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    public function test_assigned_survey_member_allowed(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Assigned Surveyor');
        $project = $this->createProject('PRJ-SA', 'project-sa', 'Survey Allowed');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $this->assignRoleToProject($member, $surveyor, $project, ['survey.view', 'survey.create', 'survey_plan.manage']);

        $plan = $this->createSurveyPlan($project, 'SURV-ALLOW');
        SurveyPlanMember::create([
            'survey_plan_id' => $plan->id,
            'member_id' => $member->id,
            'role_in_survey' => 'surveyor',
            'status' => 'assigned',
        ]);

        $response = $this->actingAs($member, 'sanctum')->getJson(
            '/api/construction/mobile/construction/survey-plans/' . $plan->id . '?role=surveyor'
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $plan->id);
    }

    public function test_unassigned_survey_member_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Unassigned Surveyor');
        $project = $this->createProject('PRJ-SU', 'project-su', 'Survey Unassigned');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $this->assignRoleToProject($member, $surveyor, $project, ['survey.view']);

        $plan = $this->createSurveyPlan($project, 'SURV-UNA');

        $response = $this->actingAs($member, 'sanctum')->getJson(
            '/api/construction/mobile/construction/survey-plans/' . $plan->id . '?role=surveyor'
        );

        $response->assertForbidden();
    }

    public function test_wrong_project_survey_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Wrong Project Surveyor');
        $projectA = $this->createProject('PRJ-SW1', 'project-sw1', 'Survey Wrong A');
        $projectB = $this->createProject('PRJ-SW2', 'project-sw2', 'Survey Wrong B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $this->assignRoleToProject($member, $surveyor, $projectA, ['survey.view']);

        $planB = $this->createSurveyPlan($projectB, 'SURV-WRONG');
        SurveyPlanMember::create([
            'survey_plan_id' => $planB->id,
            'member_id' => $member->id,
            'role_in_survey' => 'surveyor',
            'status' => 'assigned',
        ]);

        // Member has role on A but the plan belongs to B → 403.
        $response = $this->actingAs($member, 'sanctum')->getJson(
            '/api/construction/mobile/construction/survey-plans/' . $planB->id . '?role=surveyor'
        );

        $response->assertForbidden();
    }

    public function test_another_members_survey_visit_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $memberA = $this->createMember($admin, 'Visit Owner A');
        $memberB = $this->createMember($admin, 'Visit Intruder B');
        $project = $this->createProject('PRJ-SV', 'project-sv', 'Survey Visit');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $this->assignRoleToProject($memberA, $surveyor, $project, ['survey.create']);
        $this->assignRoleToProject($memberB, $surveyor, $project, ['survey.create']);

        $plan = $this->createSurveyPlan($project, 'SURV-VISIT');
        SurveyPlanMember::create(['survey_plan_id' => $plan->id, 'member_id' => $memberA->id, 'status' => 'assigned']);
        SurveyPlanMember::create(['survey_plan_id' => $plan->id, 'member_id' => $memberB->id, 'status' => 'assigned']);

        $visit = SurveyVisit::create([
            'project_id' => $project->id,
            'survey_plan_id' => $plan->id,
            'checked_in_by_member_id' => $memberA->id,
            'check_in_at' => now(),
            'status' => SurveyVisit::STATUS_IN_PROGRESS,
        ]);

        // Member B tries to add an entry to Member A's visit → 403.
        $response = $this->actingAs($memberB, 'sanctum')->postJson(
            '/api/construction/mobile/construction/survey-visits/' . $visit->id . '/entries?role=surveyor',
            [
                'entry_type' => 'note',
                'title' => 'Intruder note',
            ]
        );

        $response->assertForbidden();
    }

    public function test_inactive_survey_assignment_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Inactive Surveyor');
        $project = $this->createProject('PRJ-SI', 'project-si', 'Survey Inactive');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $this->assignRoleToProject($member, $surveyor, $project, ['survey.view']);

        $plan = $this->createSurveyPlan($project, 'SURV-INACTIVE');
        SurveyPlanMember::create([
            'survey_plan_id' => $plan->id,
            'member_id' => $member->id,
            'role_in_survey' => 'surveyor',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($member, 'sanctum')->getJson(
            '/api/construction/mobile/construction/survey-plans/' . $plan->id . '?role=surveyor'
        );

        $response->assertForbidden();
    }

    public function test_valid_role_but_no_survey_assignment_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Role Only Surveyor');
        $project = $this->createProject('PRJ-SR', 'project-sr', 'Survey Role Only');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $this->assignRoleToProject($member, $surveyor, $project, ['survey.view', 'survey.create']);

        $plan = $this->createSurveyPlan($project, 'SURV-ROLEONLY');

        // Role + permission present, but no SurveyPlanMember record → 403.
        $response = $this->actingAs($member, 'sanctum')->postJson(
            '/api/construction/mobile/construction/survey-visits/check-in?role=surveyor',
            ['survey_plan_id' => $plan->id]
        );

        $response->assertForbidden();
    }

    public function test_assigned_member_can_manage_survey_checklist(): void
{
    $admin = $this->createSuperAdmin();

    $member = $this->createMember(
        $admin,
        'Checklist Surveyor'
    );

    $project = $this->createProject(
        'PRJ-SCL',
        'project-scl',
        'Survey Checklist'
    );

    $surveyor = $this->createRole(
        'surveyor',
        'Surveyor'
    );

    $this->assignRoleToProject(
        $member,
        $surveyor,
        $project,
        [
            'survey.view',
            'survey.create',
            'survey_plan.manage',
        ]
    );

    $plan = $this->createSurveyPlan(
        $project,
        'SURV-CHECKLIST'
    );

    $assignment = SurveyPlanMember::create([
        'survey_plan_id' => $plan->getKey(),
        'member_id' => $member->getKey(),
        'role_in_survey' => 'surveyor',
        'status' => 'assigned',
    ]);

    $clientReference = (string) Str::uuid();

    $createUrl =
        '/api/construction/mobile/construction'
        . '/survey-plans/'
        . $plan->getKey()
        . '/checklist-works?role=surveyor';

    $createResponse = $this
        ->actingAs($member, 'sanctum')
        ->postJson($createUrl, [
            'work_title' =>
                '  Check   instrument and battery  ',
            'client_reference' => $clientReference,
        ]);

    $createResponse
        ->assertCreated()
        ->assertJsonPath(
            'data.work_title',
            'Check instrument and battery'
        )
        ->assertJsonPath(
            'data.source',
            SurveyWorkChecklist::SOURCE_MEMBER
        )
        ->assertJsonPath(
            'data.is_completed',
            false
        );

    $workId = $createResponse->json('data.id');

    $retryResponse = $this
        ->actingAs($member, 'sanctum')
        ->postJson($createUrl, [
            'work_title' =>
                'Check instrument and battery',
            'client_reference' => $clientReference,
        ]);

    $retryResponse
        ->assertOk()
        ->assertJsonPath('data.id', $workId);

    $this->assertDatabaseCount(
        'construction_survey_work_checklists',
        1
    );

    $this->assertDatabaseHas(
        'construction_survey_work_checklists',
        [
            'id' => $workId,
            'survey_plan_member_id' =>
                $assignment->getKey(),
            'work_title' =>
                'Check instrument and battery',
            'source' =>
                SurveyWorkChecklist::SOURCE_MEMBER,
            'status' =>
                SurveyWorkChecklist::STATUS_PENDING,
        ]
    );

    $detailResponse = $this
        ->actingAs($member, 'sanctum')
        ->getJson(
            '/api/construction/mobile/construction'
            . '/survey-plans/'
            . $plan->getKey()
            . '?role=surveyor'
        );

    $detailResponse
        ->assertOk()
        ->assertJsonPath(
            'data.current_assignment.id',
            $assignment->getKey()
        )
        ->assertJsonPath(
            'data.current_assignment'
            . '.work_checklists.0.id',
            $workId
        )
        ->assertJsonPath(
            'data.current_assignment'
            . '.work_checklists.0.is_completed',
            false
        );

    $updateUrl =
        '/api/construction/mobile/construction'
        . '/survey-plans/'
        . $plan->getKey()
        . '/checklist-works/'
        . $workId
        . '?role=surveyor';

    $completeResponse = $this
        ->actingAs($member, 'sanctum')
        ->patchJson($updateUrl, [
            'is_completed' => true,
        ]);

    $completeResponse
        ->assertOk()
        ->assertJsonPath('data.is_completed', true);

    $this->assertDatabaseHas(
        'construction_survey_work_checklists',
        [
            'id' => $workId,
            'status' =>
                SurveyWorkChecklist::STATUS_COMPLETED,
            'completed_by_member_id' =>
                $member->getKey(),
        ]
    );

    $this->assertNotNull(
        SurveyWorkChecklist::findOrFail(
            $workId
        )->completed_at
    );

    $reopenResponse = $this
        ->actingAs($member, 'sanctum')
        ->patchJson($updateUrl, [
            'is_completed' => false,
        ]);

    $reopenResponse
        ->assertOk()
        ->assertJsonPath('data.is_completed', false);

    $this->assertDatabaseHas(
        'construction_survey_work_checklists',
        [
            'id' => $workId,
            'status' =>
                SurveyWorkChecklist::STATUS_PENDING,
            'completed_by_member_id' => null,
            'completed_at' => null,
        ]
    );
}

public function test_unassigned_member_cannot_add_survey_checklist_work(): void
{
    $admin = $this->createSuperAdmin();

    $member = $this->createMember(
        $admin,
        'Unassigned Checklist Surveyor'
    );

    $project = $this->createProject(
        'PRJ-SCU',
        'project-scu',
        'Unassigned Checklist'
    );

    $surveyor = $this->createRole(
        'surveyor',
        'Surveyor'
    );

    $this->assignRoleToProject(
        $member,
        $surveyor,
        $project,
        ['survey_plan.manage']
    );

    $plan = $this->createSurveyPlan(
        $project,
        'SURV-CHECK-UNA'
    );

    $response = $this
        ->actingAs($member, 'sanctum')
        ->postJson(
            '/api/construction/mobile/construction'
            . '/survey-plans/'
            . $plan->getKey()
            . '/checklist-works?role=surveyor',
            [
                'work_title' =>
                    'Unauthorized checklist work',
                'client_reference' =>
                    (string) Str::uuid(),
            ]
        );

    $response->assertForbidden();

    $this->assertDatabaseCount(
        'construction_survey_work_checklists',
        0
    );
}

public function test_member_cannot_update_another_survey_checklist_work(): void
{
    $admin = $this->createSuperAdmin();

    $owner = $this->createMember(
        $admin,
        'Checklist Owner'
    );

    $otherMember = $this->createMember(
        $admin,
        'Other Checklist Surveyor'
    );

    $project = $this->createProject(
        'PRJ-SCO',
        'project-sco',
        'Checklist Ownership'
    );

    $surveyor = $this->createRole(
        'surveyor',
        'Surveyor'
    );

    $this->assignRoleToProject(
        $owner,
        $surveyor,
        $project,
        ['survey_plan.manage']
    );

    $this->assignRoleToProject(
        $otherMember,
        $surveyor,
        $project,
        ['survey_plan.manage']
    );

    $plan = $this->createSurveyPlan(
        $project,
        'SURV-CHECK-OWNER'
    );

    $ownerAssignment = SurveyPlanMember::create([
        'survey_plan_id' => $plan->getKey(),
        'member_id' => $owner->getKey(),
        'role_in_survey' => 'surveyor',
        'status' => 'assigned',
    ]);

    SurveyPlanMember::create([
        'survey_plan_id' => $plan->getKey(),
        'member_id' => $otherMember->getKey(),
        'role_in_survey' => 'surveyor',
        'status' => 'assigned',
    ]);

    $surveyWork = SurveyWorkChecklist::create([
        'survey_plan_member_id' =>
            $ownerAssignment->getKey(),
        'work_title' => 'Owner checklist work',
        'source' =>
            SurveyWorkChecklist::SOURCE_SUPER_ADMIN,
        'status' =>
            SurveyWorkChecklist::STATUS_PENDING,
        'added_by_type' =>
            $admin->getMorphClass(),
        'added_by_id' => $admin->getKey(),
        'sort_order' => 0,
    ]);

    $response = $this
        ->actingAs($otherMember, 'sanctum')
        ->patchJson(
            '/api/construction/mobile/construction'
            . '/survey-plans/'
            . $plan->getKey()
            . '/checklist-works/'
            . $surveyWork->getKey()
            . '?role=surveyor',
            [
                'is_completed' => true,
            ]
        );

    $response->assertNotFound();

    $this->assertDatabaseHas(
        'construction_survey_work_checklists',
        [
            'id' => $surveyWork->getKey(),
            'status' =>
                SurveyWorkChecklist::STATUS_PENDING,
            'completed_by_member_id' => null,
            'completed_at' => null,
        ]
    );
}

    /*
    |--------------------------------------------------------------------------
    | EXECUTION TASK ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    public function test_active_assignee_allowed(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Active Assignee');
        $project = $this->createProject('PRJ-EA', 'project-ea', 'Exec Active');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');
        $this->assignRoleToProject($member, $siteEmployee, $project, ['execution.task.update', 'execution_task.manage']);

        $task = $this->createExecutionTask($project, 'TASK-ACTIVE');
        ExecutionTaskAssignee::create([
            'project_id' => $project->id,
            'execution_task_id' => $task->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($member, 'sanctum')->postJson(
            '/api/construction/mobile/construction/tasks/' . $task->id . '/progress?role=site_employee',
            [
                'progress_percent' => 50,
                'status' => 'in_progress',
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('data.progress_percent', 50);
    }

    public function test_inactive_assignee_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Inactive Assignee');
        $project = $this->createProject('PRJ-EI', 'project-ei', 'Exec Inactive');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');
        $this->assignRoleToProject($member, $siteEmployee, $project, ['execution.task.update']);

        $task = $this->createExecutionTask($project, 'TASK-INACTIVE');
        ExecutionTaskAssignee::create([
            'project_id' => $project->id,
            'execution_task_id' => $task->id,
            'member_id' => $member->id,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($member, 'sanctum')->postJson(
            '/api/construction/mobile/construction/tasks/' . $task->id . '/progress?role=site_employee',
            [
                'progress_percent' => 50,
                'status' => 'in_progress',
            ]
        );

        $response->assertForbidden();
    }

    public function test_non_assignee_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $memberA = $this->createMember($admin, 'Task Owner A');
        $memberB = $this->createMember($admin, 'Task Intruder B');
        $project = $this->createProject('PRJ-EN', 'project-en', 'Exec Non Assignee');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');
        $this->assignRoleToProject($memberA, $siteEmployee, $project, ['execution.task.update']);
        $this->assignRoleToProject($memberB, $siteEmployee, $project, ['execution.task.update']);

        $task = $this->createExecutionTask($project, 'TASK-NON');
        ExecutionTaskAssignee::create([
            'project_id' => $project->id,
            'execution_task_id' => $task->id,
            'member_id' => $memberA->id,
            'status' => 'active',
        ]);

        // Member B has role + permission but is not the assignee → 403.
        $response = $this->actingAs($memberB, 'sanctum')->postJson(
            '/api/construction/mobile/construction/tasks/' . $task->id . '/progress?role=site_employee',
            [
                'progress_percent' => 50,
                'status' => 'in_progress',
            ]
        );

        $response->assertForbidden();
    }

    public function test_wrong_project_task_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Wrong Project Assignee');
        $projectA = $this->createProject('PRJ-EW1', 'project-ew1', 'Exec Wrong A');
        $projectB = $this->createProject('PRJ-EW2', 'project-ew2', 'Exec Wrong B');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');
        $this->assignRoleToProject($member, $siteEmployee, $projectA, ['execution.task.update']);

        $taskB = $this->createExecutionTask($projectB, 'TASK-WRONG');
        ExecutionTaskAssignee::create([
            'project_id' => $projectB->id,
            'execution_task_id' => $taskB->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        // Member has role on A but task belongs to B → 403.
        $response = $this->actingAs($member, 'sanctum')->postJson(
            '/api/construction/mobile/construction/tasks/' . $taskB->id . '/progress?role=site_employee',
            [
                'progress_percent' => 50,
                'status' => 'in_progress',
            ]
        );

        $response->assertForbidden();
    }

    public function test_role_permission_alone_insufficient_without_assignment(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Role Only Assignee');
        $project = $this->createProject('PRJ-ER', 'project-er', 'Exec Role Only');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');
        $this->assignRoleToProject($member, $siteEmployee, $project, ['execution.task.update']);

        $task = $this->createExecutionTask($project, 'TASK-ROLEONLY');

        // Role + permission present, but no ExecutionTaskAssignee → 403.
        $response = $this->actingAs($member, 'sanctum')->postJson(
            '/api/construction/mobile/construction/tasks/' . $task->id . '/progress?role=site_employee',
            [
                'progress_percent' => 50,
                'status' => 'in_progress',
            ]
        );

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | VEHICLE ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    public function test_correct_driver_allowed(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Correct Driver');
        $project = $this->createProject('PRJ-VC', 'project-vc', 'Vehicle Correct');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');
        $this->assignRoleToProject($member, $driver, $project, ['vehicle_tracking.manage']);

        $vehicle = $this->createVehicle($project, 'VEH-CORRECT');
        VehicleAssignment::create([
            'project_id' => $project->id,
            'vehicle_id' => $vehicle->id,
            'driver_member_id' => $member->id,
            'assigned_from' => now(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($member, 'sanctum')->postJson(
            '/api/construction/mobile/construction/projects/' . $project->id . '/vehicles/pings?role=vehicle_driver',
            [
                'vehicle_id' => $vehicle->id,
                'latitude' => 19.076,
                'longitude' => 72.877,
            ]
        );

        $response->assertCreated();
        $response->assertJsonPath('data.vehicle_id', $vehicle->id);
    }

    public function test_wrong_driver_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $memberA = $this->createMember($admin, 'Driver A');
        $memberB = $this->createMember($admin, 'Driver B');
        $project = $this->createProject('PRJ-VW', 'project-vw', 'Vehicle Wrong Driver');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');
        $this->assignRoleToProject($memberA, $driver, $project, ['vehicle_tracking.manage']);
        $this->assignRoleToProject($memberB, $driver, $project, ['vehicle_tracking.manage']);

        $vehicle = $this->createVehicle($project, 'VEH-WRONGDRIVER');
        VehicleAssignment::create([
            'project_id' => $project->id,
            'vehicle_id' => $vehicle->id,
            'driver_member_id' => $memberA->id,
            'assigned_from' => now(),
            'status' => 'active',
        ]);

        // Member B has role + permission but is not the assigned driver → 403.
        $response = $this->actingAs($memberB, 'sanctum')->postJson(
            '/api/construction/mobile/construction/projects/' . $project->id . '/vehicles/pings?role=vehicle_driver',
            [
                'vehicle_id' => $vehicle->id,
                'latitude' => 19.076,
                'longitude' => 72.877,
            ]
        );

        $response->assertForbidden();
    }

    public function test_inactive_vehicle_assignment_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Inactive Driver');
        $project = $this->createProject('PRJ-VI', 'project-vi', 'Vehicle Inactive');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');
        $this->assignRoleToProject($member, $driver, $project, ['vehicle_tracking.manage']);

        $vehicle = $this->createVehicle($project, 'VEH-INACTIVE');
        VehicleAssignment::create([
            'project_id' => $project->id,
            'vehicle_id' => $vehicle->id,
            'driver_member_id' => $member->id,
            'assigned_from' => now(),
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($member, 'sanctum')->postJson(
            '/api/construction/mobile/construction/projects/' . $project->id . '/vehicles/pings?role=vehicle_driver',
            [
                'vehicle_id' => $vehicle->id,
                'latitude' => 19.076,
                'longitude' => 72.877,
            ]
        );

        $response->assertForbidden();
    }

    public function test_wrong_project_vehicle_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Wrong Project Driver');
        $projectA = $this->createProject('PRJ-VP1', 'project-vp1', 'Vehicle Wrong A');
        $projectB = $this->createProject('PRJ-VP2', 'project-vp2', 'Vehicle Wrong B');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');
        $this->assignRoleToProject($member, $driver, $projectA, ['vehicle_tracking.manage']);

        $vehicleB = $this->createVehicle($projectB, 'VEH-WRONGPROJ');
        VehicleAssignment::create([
            'project_id' => $projectB->id,
            'vehicle_id' => $vehicleB->id,
            'driver_member_id' => $member->id,
            'assigned_from' => now(),
            'status' => 'active',
        ]);

        // Member has role on A but vehicle belongs to B → 403.
        $response = $this->actingAs($member, 'sanctum')->postJson(
            '/api/construction/mobile/construction/projects/' . $projectA->id . '/vehicles/pings?role=vehicle_driver',
            [
                'vehicle_id' => $vehicleB->id,
                'latitude' => 19.076,
                'longitude' => 72.877,
            ]
        );

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | EQUIPMENT ALLOCATION
    |--------------------------------------------------------------------------
    */

    public function test_correct_allocation_allowed(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Correct Equipment User');
        $project = $this->createProject('PRJ-EC', 'project-ec', 'Equipment Correct');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');
        $this->assignRoleToProject($member, $siteEmployee, $project, ['equipment_usage.manage']);

        $equipment = $this->createEquipment($project, 'EQP-CORRECT');
        EquipmentAllocation::create([
            'project_id' => $project->id,
            'equipment_id' => $equipment->id,
            'assigned_to_member_id' => $member->id,
            'allocated_at' => now(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($member, 'sanctum')->postJson(
            '/api/construction/mobile/construction/projects/' . $project->id . '/equipment/usage?role=site_employee',
            [
                'equipment_id' => $equipment->id,
                'log_date' => now()->toDateString(),
                'hours_used' => 2.5,
            ]
        );

        $response->assertCreated();
        $response->assertJsonPath('data.equipment_id', $equipment->id);
    }

    public function test_wrong_project_allocation_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Wrong Project Equipment');
        $projectA = $this->createProject('PRJ-EP1', 'project-ep1', 'Equipment Wrong A');
        $projectB = $this->createProject('PRJ-EP2', 'project-ep2', 'Equipment Wrong B');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');
        $this->assignRoleToProject($member, $siteEmployee, $projectA, ['equipment_usage.manage']);

        $equipmentB = $this->createEquipment($projectB, 'EQP-WRONGPROJ');
        EquipmentAllocation::create([
            'project_id' => $projectB->id,
            'equipment_id' => $equipmentB->id,
            'assigned_to_member_id' => $member->id,
            'allocated_at' => now(),
            'status' => 'active',
        ]);

        // Member has role on A but equipment belongs to B → 403.
        $response = $this->actingAs($member, 'sanctum')->postJson(
            '/api/construction/mobile/construction/projects/' . $projectA->id . '/equipment/usage?role=site_employee',
            [
                'equipment_id' => $equipmentB->id,
                'log_date' => now()->toDateString(),
                'hours_used' => 2.5,
            ]
        );

        $response->assertForbidden();
    }

    public function test_wrong_member_allocation_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $memberA = $this->createMember($admin, 'Equipment Owner A');
        $memberB = $this->createMember($admin, 'Equipment Intruder B');
        $project = $this->createProject('PRJ-EM', 'project-em', 'Equipment Wrong Member');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');
        $this->assignRoleToProject($memberA, $siteEmployee, $project, ['equipment_usage.manage']);
        $this->assignRoleToProject($memberB, $siteEmployee, $project, ['equipment_usage.manage']);

        $equipment = $this->createEquipment($project, 'EQP-WRONGMEMBER');
        EquipmentAllocation::create([
            'project_id' => $project->id,
            'equipment_id' => $equipment->id,
            'assigned_to_member_id' => $memberA->id,
            'allocated_at' => now(),
            'status' => 'active',
        ]);

        // Member B has role + permission but no allocation → 403.
        $response = $this->actingAs($memberB, 'sanctum')->postJson(
            '/api/construction/mobile/construction/projects/' . $project->id . '/equipment/usage?role=site_employee',
            [
                'equipment_id' => $equipment->id,
                'log_date' => now()->toDateString(),
                'hours_used' => 2.5,
            ]
        );

        $response->assertForbidden();
    }

    public function test_inactive_allocation_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Inactive Equipment User');
        $project = $this->createProject('PRJ-EI2', 'project-ei2', 'Equipment Inactive');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');
        $this->assignRoleToProject($member, $siteEmployee, $project, ['equipment_usage.manage']);

        $equipment = $this->createEquipment($project, 'EQP-INACTIVE');
        EquipmentAllocation::create([
            'project_id' => $project->id,
            'equipment_id' => $equipment->id,
            'assigned_to_member_id' => $member->id,
            'allocated_at' => now(),
            'status' => 'returned',
        ]);

        $response = $this->actingAs($member, 'sanctum')->postJson(
            '/api/construction/mobile/construction/projects/' . $project->id . '/equipment/usage?role=site_employee',
            [
                'equipment_id' => $equipment->id,
                'log_date' => now()->toDateString(),
                'hours_used' => 2.5,
            ]
        );

        $response->assertForbidden();
    }

    public function test_permission_present_but_assignment_missing_denied(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Role Only Equipment');
        $project = $this->createProject('PRJ-ER2', 'project-er2', 'Equipment Role Only');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');
        $this->assignRoleToProject($member, $siteEmployee, $project, ['equipment_usage.manage']);

        $equipment = $this->createEquipment($project, 'EQP-ROLEONLY');

        // Role + permission present, but no EquipmentAllocation → 403.
        $response = $this->actingAs($member, 'sanctum')->postJson(
            '/api/construction/mobile/construction/projects/' . $project->id . '/equipment/usage?role=site_employee',
            [
                'equipment_id' => $equipment->id,
                'log_date' => now()->toDateString(),
                'hours_used' => 2.5,
            ]
        );

        $response->assertForbidden();
    }
}