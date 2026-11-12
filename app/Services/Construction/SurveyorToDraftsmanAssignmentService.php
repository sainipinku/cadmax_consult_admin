<?php

namespace App\Services\Construction;

use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\DraftingJob;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\Role;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Support\Construction\SurveyStatus;

class SurveyorToDraftsmanAssignmentService
{
    private const SURVEYOR_ROLE_SLUGS = ['surveyor', 'survey', 'field_surveyor'];
    private const DRAFTSMAN_ROLE_SLUGS = ['draft_person', 'draftsman', 'drafter', 'draft_man'];

    public function assign(
        Project $project,
        string $taskSource,
        int $taskId,
        int $memberId,
        ?string $dueDate,
        Model $actor,
        ?Request $request = null
    ): Model {
        return DB::transaction(function () use ($project, $taskSource, $taskId, $memberId, $dueDate, $actor, $request) {
            $task = match ($taskSource) {
                'execution' => ExecutionTask::query()
                    ->where('project_id', $project->id)
                    ->lockForUpdate()
                    ->find($taskId),
                'task' => Task::query()
                    ->where('project_id', $project->id)
                    ->lockForUpdate()
                    ->find($taskId),
                default => null,
            };

            if (!$task) {
                throw ValidationException::withMessages([
                    'task_id' => ['The selected task does not belong to this project.'],
                ]);
            }

            if (strtolower((string) $task->status) !== 'completed') {
                throw ValidationException::withMessages([
                    'task_id' => ['A Surveyor task must be completed before it can be assigned to a Draftsman.'],
                ]);
            }

            if (!$this->hasSurveyorAssignment($task, $project)) {
                throw ValidationException::withMessages([
                    'task_id' => ['The completed task must have an assigned Surveyor before Draftsman assignment.'],
                ]);
            }

            $draftsman = Member::query()->find($memberId);
            if (!$draftsman || !$this->isAssignedDraftsman($project, $draftsman)) {
                throw ValidationException::withMessages([
                    'member_id' => ['Select an active Draftsman assigned to this project.'],
                ]);
            }

            if ($task instanceof ExecutionTask) {
                $this->assignExecutionTask($task, $draftsman, $dueDate, $actor, $project, $request);
            } else {
                $this->assignUnifiedTask($task, $draftsman, $dueDate, $actor, $project, $request);
            }

            return $task->refresh();
        });
    }

    private function hasSurveyorAssignment(ExecutionTask|Task $task, Project $project): bool
    {
        if ($task instanceof ExecutionTask) {
            $assignments = $task->assignees()
                ->where('status', 'active')
                ->with('member')
                ->get();

            foreach ($assignments as $assignment) {
                if ($this->isSurveyorRole($assignment->assignment_role)
                    || $this->memberHasRole($project, (int) $assignment->member_id, self::SURVEYOR_ROLE_SLUGS)) {
                    return true;
                }
            }

            return $task->supervisor_member_id
                && $this->memberHasRole($project, (int) $task->supervisor_member_id, self::SURVEYOR_ROLE_SLUGS);
        }

        foreach ($task->activeAssignments()->with('assignee')->get() as $assignment) {
            if ($this->isSurveyorRole($assignment->assignment_role)
                || $this->memberHasRole($project, (int) $assignment->assigned_to, self::SURVEYOR_ROLE_SLUGS)) {
                return true;
            }
        }

        return $task->assigned_supervisor_member_id
            && $this->memberHasRole($project, (int) $task->assigned_supervisor_member_id, self::SURVEYOR_ROLE_SLUGS);
    }

    private function assignExecutionTask(
        ExecutionTask $task,
        Member $draftsman,
        ?string $dueDate,
        Model $actor,
        Project $project,
        ?Request $request
    ): void {
        $surveyorMemberIds = $task->assignees()
            ->where('status', 'active')
            ->get()
            ->filter(fn ($assignment) => $this->isSurveyorRole($assignment->assignment_role)
                || $this->memberHasRole($project, (int) $assignment->member_id, self::SURVEYOR_ROLE_SLUGS))
            ->pluck('member_id')
            ->values()
            ->all();

        $activeDraftsmanAssignment = $task->assignees()
            ->where('status', 'active')
            ->whereIn('assignment_role', self::DRAFTSMAN_ROLE_SLUGS)
            ->exists();

        if ($activeDraftsmanAssignment) {
            throw ValidationException::withMessages([
                'member_id' => ['This task already has an active Draftsman assignment.'],
            ]);
        }

        $task->assignees()
            ->where('status', 'active')
            ->get()
            ->filter(fn ($assignment) => $this->isSurveyorRole($assignment->assignment_role)
                || $this->memberHasRole($project, (int) $assignment->member_id, self::SURVEYOR_ROLE_SLUGS))
            ->each(fn ($assignment) => $assignment->update(['status' => 'inactive']));

        ExecutionTaskAssignee::updateOrCreate(
            [
                'execution_task_id' => $task->id,
                'member_id' => $draftsman->id,
            ],
            [
                'project_id' => $project->id,
                'assignment_role' => 'draft_person',
                'assigned_from' => now()->toDateString(),
                'assigned_to' => $dueDate,
                'is_primary' => true,
                'assigned_by_type' => $actor::class,
                'assigned_by_id' => $actor->getKey(),
                'status' => 'active',
            ]
        );

        $task->update(['status' => 'in_progress']);

        app(ConstructionActivityService::class)->log(
            module: 'execution_task_assignment',
            action: 'draftsman_assigned',
            actor: $actor,
            reference: $task,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'task_source' => 'execution',
                'task_id' => $task->id,
                'previous_status' => 'completed',
                'new_status' => 'in_progress',
                'surveyor_member_ids' => $surveyorMemberIds,
                'draftsman_member_id' => $draftsman->id,
                'draftsman_name' => $draftsman->name,
                'assigned_at' => now()->toIso8601String(),
            ],
            request: $request
        );

        $this->createOrUpdateDraftingJob($task, $surveyorMemberIds, $draftsman, $dueDate, $actor, $project);
    }

    private function assignUnifiedTask(
        Task $task,
        Member $draftsman,
        ?string $dueDate,
        Model $actor,
        Project $project,
        ?Request $request
    ): void {
        $surveyorMemberIds = $task->activeAssignments()
            ->get()
            ->filter(fn ($assignment) => $this->isSurveyorRole($assignment->assignment_role)
                || $this->memberHasRole($project, (int) $assignment->assigned_to, self::SURVEYOR_ROLE_SLUGS))
            ->pluck('assigned_to')
            ->values()
            ->all();

        $activeDraftsmanAssignment = $task->activeAssignments()
            ->whereIn('assignment_role', self::DRAFTSMAN_ROLE_SLUGS)
            ->exists();

        if ($activeDraftsmanAssignment) {
            throw ValidationException::withMessages([
                'member_id' => ['This task already has an active Draftsman assignment.'],
            ]);
        }

        $task->activeAssignments()
            ->get()
            ->filter(fn ($assignment) => $this->isSurveyorRole($assignment->assignment_role)
                || $this->memberHasRole($project, (int) $assignment->assigned_to, self::SURVEYOR_ROLE_SLUGS))
            ->each(fn ($assignment) => $assignment->update([
                'status' => 'completed',
                'is_transferred' => true,
            ]));

        $assignment = TaskAssignment::withTrashed()
            ->where('task_id', $task->id)
            ->where('assigned_to', $draftsman->id)
            ->first();

        if (!$assignment) {
            $assignment = new TaskAssignment([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'task_id' => $task->id,
                'project_id' => $project->id,
                'assigned_to' => $draftsman->id,
                'assigned_by' => $actor->getKey(),
            ]);
        } elseif ($assignment->trashed()) {
            $assignment->restore();
        }

        $assignment->fill([
            'project_id' => $project->id,
            'assignment_role' => 'draft_person',
            'assigned_from' => now()->toDateString(),
            'assigned_until' => $dueDate,
            'assigned_by' => $actor->getKey(),
            'assigned_by_type' => $actor::class,
            'assigned_by_uid' => $actor->getKey(),
            'status' => 'active',
            'is_primary' => true,
            'is_transferred' => false,
        ])->save();

        $task->update(['status' => 'in_progress']);

        app(ConstructionActivityService::class)->log(
            module: 'task_assignment',
            action: 'draftsman_assigned',
            actor: $actor,
            reference: $task,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'task_source' => 'task',
                'task_id' => $task->id,
                'previous_status' => 'completed',
                'new_status' => 'in_progress',
                'surveyor_member_ids' => $surveyorMemberIds,
                'draftsman_member_id' => $draftsman->id,
                'draftsman_name' => $draftsman->name,
                'assigned_at' => now()->toIso8601String(),
            ],
            request: $request
        );

        $this->createOrUpdateDraftingJob($task, $surveyorMemberIds, $draftsman, $dueDate, $actor, $project);
    }

    private function createOrUpdateDraftingJob(
        ExecutionTask|Task $task,
        array $surveyorMemberIds,
        Member $draftsman,
        ?string $dueDate,
        Model $actor,
        Project $project
    ): void {
        $taskColumn = $task instanceof ExecutionTask ? 'execution_task_id' : 'task_id';
        $surveyorMemberId = (int) ($surveyorMemberIds[0]
            ?? ($task instanceof ExecutionTask ? $task->supervisor_member_id : $task->assigned_supervisor_member_id));

        $surveyVisit = SurveyVisit::query()
            ->where('project_id', $project->id)
            ->where($taskColumn, $task->id)
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if (!$surveyVisit) {
            $surveyVisit = SurveyVisit::create([
                'project_id' => $project->id,
                'survey_plan_id' => null,
                'task_id' => $task instanceof Task ? $task->id : null,
                'execution_task_id' => $task instanceof ExecutionTask ? $task->id : null,
                'member_id' => $surveyorMemberId,
                'checked_in_by_member_id' => $surveyorMemberId,
                'check_in_at' => now(),
                'check_out_at' => now(),
                'submitted_at' => now(),
                'status' => SurveyStatus::APPROVED,
            ]);
        }

        $surveySubmission = SurveySubmission::query()
            ->where('survey_visit_id', $surveyVisit->id)
            ->latest('id')
            ->first();

        if (!$surveySubmission) {
            $surveySubmission = SurveySubmission::create([
                'project_id' => $project->id,
                'survey_visit_id' => $surveyVisit->id,
                'submitted_by_member_id' => $surveyVisit->checked_in_by_member_id,
                'submitted_at' => $surveyVisit->submitted_at ?? now(),
                'status' => SurveyStatus::APPROVED,
                'reviewed_by_member_id' => $actor instanceof Member ? $actor->getKey() : null,
                'reviewed_at' => now(),
            ]);
        } else {
            $surveySubmission->update([
                'status' => SurveyStatus::APPROVED,
                'reviewed_by_member_id' => $actor instanceof Member ? $actor->getKey() : null,
                'reviewed_at' => now(),
            ]);
        }

        DraftingJob::updateOrCreate(
            [
                'project_id' => $project->id,
                'survey_submission_id' => $surveySubmission->id,
            ],
            [
                'assigned_to_member_id' => $draftsman->id,
                'assigned_by_type' => $actor::class,
                'assigned_by_id' => $actor->getKey(),
                'assigned_at' => now(),
                'due_date' => $dueDate,
                'status' => 'in_progress',
            ]
        );

        $project->update(['current_stage' => 'drafting_in_progress']);
    }

    private function isAssignedDraftsman(Project $project, Member $member): bool
    {
        $activeTeamMember = ProjectTeamMember::query()
            ->where('project_id', $project->id)
            ->where('member_id', $member->id)
            ->where('status', 'active')
            ->exists();
        $hasProjectRoleAssignment = MemberRoleAssignment::query()
            ->where('project_id', $project->id)
            ->where('member_id', $member->id)
            ->where('status', 1)
            ->exists();

        if (!$activeTeamMember && !$hasProjectRoleAssignment) {
            return false;
        }

        $teamRole = ProjectTeamMember::query()
            ->where('project_id', $project->id)
            ->where('member_id', $member->id)
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->whereIn('slug', self::DRAFTSMAN_ROLE_SLUGS))
            ->exists();

        if ($teamRole) {
            return true;
        }

        return MemberRoleAssignment::query()
            ->where('member_id', $member->id)
            ->where('status', 1)
            ->where(function ($query) use ($project, $activeTeamMember) {
                $query->where('project_id', $project->id);
                if ($activeTeamMember) {
                    $query->orWhereNull('project_id');
                }
            })
            ->whereHas('role', fn ($query) => $query->whereIn('slug', self::DRAFTSMAN_ROLE_SLUGS))
            ->exists();
    }

    private function memberHasRole(Project $project, int $memberId, array $slugs): bool
    {
        $activeTeamMember = ProjectTeamMember::query()
            ->where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->exists();
        $hasProjectRoleAssignment = MemberRoleAssignment::query()
            ->where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->where('status', 1)
            ->exists();

        if (!$activeTeamMember && !$hasProjectRoleAssignment) {
            return false;
        }

        $teamRole = ProjectTeamMember::query()
            ->where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $slugs))
            ->exists();

        if ($teamRole) {
            return true;
        }

        $constructionRole = MemberRoleAssignment::query()
            ->where('member_id', $memberId)
            ->where('status', 1)
            ->where(function ($query) use ($project, $activeTeamMember) {
                $query->where('project_id', $project->id);
                if ($activeTeamMember) {
                    $query->orWhereNull('project_id');
                }
            })
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $slugs))
            ->exists();

        if ($constructionRole) {
            return true;
        }

        $legacyRoleIds = Role::query()->whereIn('slug', $slugs)->pluck('id')->all();
        if ($legacyRoleIds === []) {
            return false;
        }

        $member = Member::query()->find($memberId);
        $storedRoles = $member?->getRawOriginal('roles');
        $storedRoles = is_array($storedRoles) ? $storedRoles : (json_decode((string) $storedRoles, true) ?: []);
        if (array_intersect($legacyRoleIds, array_map('intval', $storedRoles)) !== []) {
            return true;
        }

        return Member::query()
            ->whereKey($memberId)
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', $slugs))
            ->exists();
    }

    private function isSurveyorRole(mixed $assignmentRole): bool
    {
        $role = $assignmentRole instanceof \BackedEnum ? $assignmentRole->value : (string) $assignmentRole;

        return in_array(strtolower(trim($role)), self::SURVEYOR_ROLE_SLUGS, true);
    }
}
