<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Services\Construction\SurveyorToDraftsmanAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SurveyorToDraftsmanAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private int $creatorId;

    public function test_completed_surveyor_task_can_be_assigned_to_project_draftsman(): void
    {
        [$project, $surveyor, $draftsman, $actor, $task] = $this->createWorkflow();
        $service = app(SurveyorToDraftsmanAssignmentService::class);

        $service->assign($project, 'execution', $task->id, $draftsman->id, null, $actor);

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertDatabaseHas('construction_execution_task_assignees', [
            'execution_task_id' => $task->id,
            'member_id' => $draftsman->id,
            'assignment_role' => 'draft_person',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('construction_execution_task_assignees', [
            'execution_task_id' => $task->id,
            'member_id' => $surveyor->id,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('construction_activity_logs', [
            'project_id' => $project->id,
            'module' => 'execution_task_assignment',
            'action' => 'draftsman_assigned',
            'reference_id' => $task->id,
        ]);
        $surveyVisit = \App\Models\SurveyVisit::where('execution_task_id', $task->id)->firstOrFail();
        $surveySubmission = \App\Models\SurveySubmission::where('survey_visit_id', $surveyVisit->id)->firstOrFail();
        $this->assertDatabaseHas('construction_survey_submissions', [
            'survey_visit_id' => $surveyVisit->id,
            'status' => \App\Models\SurveySubmission::STATUS_APPROVED,
        ]);
        $this->assertDatabaseHas('construction_drafting_jobs', [
            'project_id' => $project->id,
            'survey_submission_id' => $surveySubmission->id,
            'assigned_to_member_id' => $draftsman->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_draftsman_assignment_is_rejected_before_surveyor_completion(): void
    {
        [$project, , $draftsman, $actor, $task] = $this->createWorkflow('in_progress');

        try {
            app(SurveyorToDraftsmanAssignmentService::class)
                ->assign($project, 'execution', $task->id, $draftsman->id, null, $actor);
            $this->fail('Expected assigning an incomplete task to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['A Surveyor task must be completed before it can be assigned to a Draftsman.'],
                $exception->errors()['task_id']
            );
        }
    }

    public function test_non_draftsman_project_member_cannot_be_assigned(): void
    {
        [$project, , , $actor, $task] = $this->createWorkflow();
        $otherMember = $this->createMember('Other Employee');
        $this->addProjectMember($project, $otherMember, 'site_employee');

        try {
            app(SurveyorToDraftsmanAssignmentService::class)
                ->assign($project, 'execution', $task->id, $otherMember->id, null, $actor);
            $this->fail('Expected assigning a non-Draftsman to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Select an active Draftsman assigned to this project.'],
                $exception->errors()['member_id']
            );
        }
    }

    public function test_duplicate_active_draftsman_assignment_is_rejected(): void
    {
        [$project, , $draftsman, $actor, $task] = $this->createWorkflow();
        $service = app(SurveyorToDraftsmanAssignmentService::class);
        $service->assign($project, 'execution', $task->id, $draftsman->id, null, $actor);
        ExecutionTask::whereKey($task->id)->update(['status' => 'completed']);

        try {
            $service->assign($project, 'execution', $task->id, $draftsman->id, null, $actor);
            $this->fail('Expected duplicate Draftsman assignment to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'member_id' => ['This task already has an active Draftsman assignment.'],
            ], $exception->errors());
        }
    }

    public function test_unified_task_is_assigned_without_changing_its_task_reference(): void
    {
        [$project, $surveyor, $draftsman, $actor] = $this->createWorkflow();
        $task = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Unified survey task',
            'project_id' => $project->id,
            'task_code' => 'TSK-' . Str::upper(Str::random(6)),
            'status' => 'completed',
            'created_by' => $actor->id,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $task->id,
            'project_id' => $project->id,
            'assigned_to' => $surveyor->id,
            'assigned_by' => $actor->id,
            'assignment_role' => 'surveyor',
            'status' => 'active',
        ]);

        app(SurveyorToDraftsmanAssignmentService::class)
            ->assign($project, 'task', $task->id, $draftsman->id, null, $actor);

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'project_id' => $project->id,
            'assigned_to' => $draftsman->id,
            'assignment_role' => 'draft_person',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'assigned_to' => $surveyor->id,
            'assignment_role' => 'surveyor',
            'status' => 'completed',
            'is_transferred' => 1,
        ]);
        $surveyVisit = \App\Models\SurveyVisit::where('task_id', $task->id)->firstOrFail();
        $surveySubmission = \App\Models\SurveySubmission::where('survey_visit_id', $surveyVisit->id)->firstOrFail();
        $this->assertDatabaseHas('construction_drafting_jobs', [
            'project_id' => $project->id,
            'survey_submission_id' => $surveySubmission->id,
            'assigned_to_member_id' => $draftsman->id,
            'status' => 'in_progress',
        ]);
    }

    private function createWorkflow(string $taskStatus = 'completed'): array
    {
        $actor = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'roles' => json_encode(['super_admin']),
            'name' => 'Construction Super Admin',
            'email' => 'super-admin-' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
        ]);
        $this->creatorId = $actor->id;
        $company = Company::create([
            'name' => 'Workflow Company ' . Str::random(5),
            'status' => 'active',
        ]);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(6)),
            'name' => 'Workflow Client',
            'status' => 'active',
        ]);
        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Workflow Project',
            'slug' => 'workflow-' . Str::lower(Str::random(6)),
            'status' => 'active',
        ]);

        $surveyor = $this->createMember('Surveyor');
        $draftsman = $this->createMember('Draftsman');
        $this->addProjectMember($project, $surveyor, 'surveyor');
        $this->addProjectMember($project, $draftsman, 'draft_person');

        $plan = ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'EXP-' . Str::upper(Str::random(6)),
            'title' => 'Survey Plan',
            'status' => 'planned',
        ]);
        $task = ExecutionTask::create([
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'EXT-' . Str::upper(Str::random(6)),
            'title' => 'Survey task',
            'priority' => 'medium',
            'supervisor_member_id' => $surveyor->id,
            'status' => $taskStatus,
        ]);
        ExecutionTaskAssignee::create([
            'project_id' => $project->id,
            'execution_task_id' => $task->id,
            'member_id' => $surveyor->id,
            'assignment_role' => 'surveyor',
            'assigned_by_type' => $actor::class,
            'assigned_by_id' => $actor->id,
            'status' => 'active',
        ]);

        return [$project, $surveyor, $draftsman, $actor, $task];
    }

    private function createMember(string $name): Member
    {
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(5));

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $this->creatorId,
            'name' => $name,
            'username' => $slug,
            'email' => $slug . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'slug' => $slug,
        ]);
    }

    private function addProjectMember(Project $project, Member $member, string $roleSlug): void
    {
        $role = ConstructionRole::create([
            'company_id' => $project->company_id,
            'name' => ucfirst(str_replace('_', ' ', $roleSlug)),
            'slug' => $roleSlug,
            'is_system_role' => true,
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }
}
