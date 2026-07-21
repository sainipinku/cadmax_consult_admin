<?php

namespace App\Services\Construction;

use App\Models\Construction\AttendanceRecord;
use App\Models\Construction\DailyProgressItem;
use App\Models\Construction\DailyProgressReport;
use App\Models\Construction\ExecutionPlan;
use App\Models\Construction\ExecutionTask;
use App\Models\Construction\ExecutionTaskAssignee;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectTeamMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConstructionExecutionService
{
    public function __construct(
        private readonly ConstructionActivityService $activityService,
        private readonly ConstructionDocumentService $documentService
    ) {
    }

    public function createPlan(Project $project, array $validated, ?Model $actor, ?Request $request = null): ExecutionPlan
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $plan = ExecutionPlan::create([
                'project_id' => $project->id,
                'plan_code' => 'EXP-' . str_pad((string) ((ExecutionPlan::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT),
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'planned_start_date' => $validated['planned_start_date'] ?? null,
                'planned_end_date' => $validated['planned_end_date'] ?? null,
                'planned_progress_percent' => 0,
                'actual_progress_percent' => 0,
                'created_by_type' => $actor ? $actor::class : null,
                'created_by_id' => $actor?->getKey(),
                'approved_by_type' => $validated['status'] === 'active' && $actor ? $actor::class : null,
                'approved_by_id' => $validated['status'] === 'active' ? $actor?->getKey() : null,
                'approved_at' => $validated['status'] === 'active' ? now() : null,
                'status' => $validated['status'],
            ]);

            if (in_array($project->current_stage, ['ready_for_construction', 'drawing_approval_pending'], true)) {
                $project->update(['current_stage' => 'execution_planned']);
            }

            $this->activityService->log(
                module: 'execution_plan',
                action: 'created',
                actor: $actor,
                reference: $plan,
                companyId: $project->company_id,
                projectId: $project->id,
                request: $request
            );

            return $plan;
        });
    }

    public function createTask(Project $project, array $validated, ?Model $actor, ?Request $request = null): ExecutionTask
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $this->ensurePlanBelongsToProject($project, (int) $validated['execution_plan_id']);

            if (!empty($validated['parent_task_id'])) {
                $this->ensureTaskBelongsToProject($project, (int) $validated['parent_task_id'], 'parent_task_id');
            }

            if (!empty($validated['supervisor_member_id'])) {
                $this->ensureMemberBelongsToProject($project, (int) $validated['supervisor_member_id'], 'supervisor_member_id');
            }

            $task = ExecutionTask::create([
                'project_id' => $project->id,
                'execution_plan_id' => $validated['execution_plan_id'],
                'parent_task_id' => $validated['parent_task_id'] ?? null,
                'task_code' => 'EXT-' . str_pad((string) ((ExecutionTask::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT),
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'planned_start_date' => $validated['planned_start_date'] ?? null,
                'planned_end_date' => $validated['planned_end_date'] ?? null,
                'priority' => $validated['priority'],
                'planned_quantity' => $validated['planned_quantity'] ?? null,
                'completed_quantity' => 0,
                'unit' => $validated['unit'] ?? null,
                'progress_percent' => 0,
                'requires_daily_update' => (bool) ($validated['requires_daily_update'] ?? true),
                'requires_gps_verification' => (bool) ($validated['requires_gps_verification'] ?? true),
                'supervisor_member_id' => $validated['supervisor_member_id'] ?? null,
                'status' => 'planned',
            ]);

            foreach ($validated['assignee_member_ids'] ?? [] as $memberId) {
                $this->assignTask(
                    $task,
                    [
                        'member_id' => $memberId,
                        'assignment_role' => $validated['primary_assignment_role'] ?? 'worker',
                        'assigned_from' => $validated['planned_start_date'] ?? null,
                        'assigned_to' => $validated['planned_end_date'] ?? null,
                        'is_primary' => count($validated['assignee_member_ids']) === 1,
                    ],
                    $actor,
                    $request
                );
            }

            $this->activityService->log(
                module: 'execution_task',
                action: 'created',
                actor: $actor,
                reference: $task,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: ['assignee_count' => count($validated['assignee_member_ids'] ?? [])],
                request: $request
            );

            return $task;
        });
    }

    public function assignTask(ExecutionTask $task, array $validated, ?Model $actor, ?Request $request = null): ExecutionTaskAssignee
    {
        $this->ensureMemberBelongsToProject($task->project, (int) $validated['member_id'], 'member_id');

        $assignment = ExecutionTaskAssignee::updateOrCreate(
            [
                'execution_task_id' => $task->id,
                'member_id' => $validated['member_id'],
            ],
            [
                'project_id' => $task->project_id,
                'assignment_role' => $validated['assignment_role'] ?? 'worker',
                'assigned_from' => $validated['assigned_from'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
                'assigned_by_type' => $actor ? $actor::class : null,
                'assigned_by_id' => $actor?->getKey(),
                'status' => 'active',
            ]
        );

        $this->activityService->log(
            module: 'execution_task_assignment',
            action: 'assigned',
            actor: $actor,
            reference: $assignment,
            projectId: $task->project_id,
            request: $request
        );

        return $assignment;
    }

    public function submitDailyProgress(Project $project, array $validated, ?Model $actor, ?Request $request = null): DailyProgressReport
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            if (!empty($validated['execution_task_id'])) {
                $this->ensureTaskBelongsToProject($project, (int) $validated['execution_task_id'], 'execution_task_id');
            }

            foreach ($validated['items'] ?? [] as $index => $item) {
                if (!empty($item['execution_task_id'])) {
                    $this->ensureTaskBelongsToProject(
                        $project,
                        (int) $item['execution_task_id'],
                        "items.{$index}.execution_task_id"
                    );
                }
            }

            $supportingDocumentId = null;
            if (!empty($validated['supporting_document']) && $validated['supporting_document'] instanceof UploadedFile) {
                $document = $this->documentService->storeDocument(
                    documentable: $project,
                    actor: $actor,
                    folder: 'construction/execution/dprs',
                    file: $validated['supporting_document'],
                    companyId: $project->company_id,
                    projectId: $project->id
                );

                $supportingDocumentId = $document->id;
            }

            $report = DailyProgressReport::create([
                'project_id' => $project->id,
                'execution_task_id' => $validated['execution_task_id'] ?? null,
                'report_date' => $validated['report_date'],
                'submitted_by_member_id' => $actor?->getKey(),
                'submitted_at' => now(),
                'summary' => $validated['summary'] ?? null,
                'work_completed' => $validated['work_completed'] ?? null,
                'blockers' => $validated['blockers'] ?? null,
                'workforce_count' => (int) ($validated['workforce_count'] ?? 0),
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'gps_accuracy_meters' => $validated['gps_accuracy_meters'] ?? null,
                'weather_summary' => $validated['weather_summary'] ?? null,
                'supporting_document_id' => $supportingDocumentId,
                'status' => 'submitted',
            ]);

            foreach ($validated['items'] ?? [] as $item) {
                DailyProgressItem::create([
                    'project_id' => $project->id,
                    'daily_progress_report_id' => $report->id,
                    'execution_task_id' => $item['execution_task_id'] ?? $validated['execution_task_id'] ?? null,
                    'title' => $item['title'],
                    'description' => $item['description'] ?? null,
                    'unit' => $item['unit'] ?? null,
                    'planned_quantity' => $item['planned_quantity'] ?? null,
                    'completed_quantity' => $item['completed_quantity'] ?? 0,
                    'percent_complete' => $item['percent_complete'] ?? 0,
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            if ($report->execution_task_id) {
                $task = ExecutionTask::find($report->execution_task_id);
                if ($task) {
                    $task->update([
                        'actual_start_date' => $task->actual_start_date ?: $report->report_date,
                        'status' => 'in_progress',
                    ]);
                }
            }

            if (in_array($project->current_stage, ['ready_for_construction', 'execution_planned'], true)) {
                $project->update(['current_stage' => 'construction_in_progress']);
            }

            $this->refreshPlanProgress($project);

            $this->activityService->log(
                module: 'daily_progress_report',
                action: 'submitted',
                actor: $actor,
                reference: $report,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: ['item_count' => count($validated['items'] ?? [])],
                request: $request
            );

            return $report->load(['items', 'supportingDocument']);
        });
    }

    public function reviewDailyProgress(DailyProgressReport $report, array $validated, ?Model $actor, ?Request $request = null): DailyProgressReport
    {
        $report->update([
            'status' => $validated['status'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by_member_id' => $actor?->getKey(),
            'reviewed_at' => now(),
        ]);

        $this->activityService->log(
            module: 'daily_progress_report',
            action: $validated['status'],
            actor: $actor,
            reference: $report,
            companyId: $report->project->company_id,
            projectId: $report->project_id,
            request: $request
        );

        return $report;
    }

    public function checkInAttendance(Project $project, array $validated, Model $actor, ?Request $request = null): AttendanceRecord
    {
        if (!empty($validated['execution_task_id'])) {
            $this->ensureTaskBelongsToProject($project, (int) $validated['execution_task_id'], 'execution_task_id');
        }

        $attendance = AttendanceRecord::updateOrCreate(
            [
                'project_id' => $project->id,
                'member_id' => $actor->getKey(),
                'attendance_date' => $validated['attendance_date'],
            ],
            [
                'execution_task_id' => $validated['execution_task_id'] ?? null,
                'check_in_at' => now(),
                'check_in_latitude' => $validated['check_in_latitude'] ?? null,
                'check_in_longitude' => $validated['check_in_longitude'] ?? null,
                'gps_accuracy_meters' => $validated['gps_accuracy_meters'] ?? null,
                'attendance_type' => $validated['attendance_type'] ?? 'present',
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
            ]
        );

        $this->activityService->log(
            module: 'attendance',
            action: 'check_in',
            actor: $actor,
            reference: $attendance,
            companyId: $project->company_id,
            projectId: $project->id,
            request: $request
        );

        return $attendance;
    }

    public function checkOutAttendance(AttendanceRecord $attendance, array $validated, Model $actor, ?Request $request = null): AttendanceRecord
    {
        $attendance->update([
            'check_out_at' => now(),
            'check_out_latitude' => $validated['check_out_latitude'] ?? null,
            'check_out_longitude' => $validated['check_out_longitude'] ?? null,
            'gps_accuracy_meters' => $validated['gps_accuracy_meters'] ?? $attendance->gps_accuracy_meters,
            'notes' => $validated['notes'] ?? $attendance->notes,
        ]);

        $this->activityService->log(
            module: 'attendance',
            action: 'check_out',
            actor: $actor,
            reference: $attendance,
            companyId: $attendance->project->company_id,
            projectId: $attendance->project_id,
            request: $request
        );

        return $attendance;
    }

    public function reviewAttendance(AttendanceRecord $attendance, array $validated, ?Model $actor, ?Request $request = null): AttendanceRecord
    {
        $attendance->update([
            'status' => $validated['status'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by_member_id' => $actor?->getKey(),
            'reviewed_at' => now(),
        ]);

        $this->activityService->log(
            module: 'attendance',
            action: $validated['status'],
            actor: $actor,
            reference: $attendance,
            companyId: $attendance->project->company_id,
            projectId: $attendance->project_id,
            request: $request
        );

        return $attendance;
    }

    public function updateTaskProgress(ExecutionTask $task, array $validated, Model $actor, ?Request $request = null): ExecutionTask
    {
        $progress = max(0, min(100, (float) ($validated['progress_percent'] ?? $task->progress_percent)));
        $completedQuantity = $validated['completed_quantity'] ?? $task->completed_quantity;
        $status = $validated['status'] ?? ($progress >= 100 ? 'completed' : 'in_progress');

        $task->update([
            'actual_start_date' => $task->actual_start_date ?: now()->toDateString(),
            'actual_end_date' => $status === 'completed' ? now()->toDateString() : null,
            'completed_quantity' => $completedQuantity,
            'progress_percent' => $progress,
            'status' => $status,
        ]);

        $this->refreshPlanProgress($task->project);

        $this->activityService->log(
            module: 'execution_task',
            action: 'progress_updated',
            actor: $actor,
            reference: $task,
            companyId: $task->project->company_id,
            projectId: $task->project_id,
            request: $request
        );

        return $task;
    }

    public function refreshPlanProgress(Project $project): void
    {
        foreach ($project->executionPlans()->with('tasks')->get() as $plan) {
            $averageProgress = (float) $plan->tasks()->avg('progress_percent');
            $plan->update([
                'actual_progress_percent' => round($averageProgress, 2),
                'status' => $averageProgress >= 100 ? 'completed' : ($averageProgress > 0 ? 'active' : $plan->status),
            ]);
        }
    }

    private function ensurePlanBelongsToProject(Project $project, int $planId): void
    {
        $belongsToProject = ExecutionPlan::whereKey($planId)
            ->where('project_id', $project->id)
            ->exists();

        if (!$belongsToProject) {
            throw ValidationException::withMessages([
                'execution_plan_id' => 'The selected execution plan does not belong to the chosen project.',
            ]);
        }
    }

    private function ensureTaskBelongsToProject(Project $project, int $taskId, string $field): void
    {
        $belongsToProject = ExecutionTask::whereKey($taskId)
            ->where('project_id', $project->id)
            ->exists();

        if (!$belongsToProject) {
            throw ValidationException::withMessages([
                $field => 'The selected task does not belong to the chosen project.',
            ]);
        }
    }

    private function ensureMemberBelongsToProject(Project $project, int $memberId, string $field): void
    {
        $belongsToProject = ProjectTeamMember::where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->exists();

        if (!$belongsToProject) {
            throw ValidationException::withMessages([
                $field => 'The selected member is not assigned to the chosen project.',
            ]);
        }
    }
}
