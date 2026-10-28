<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\DraftingJob;
use App\Models\DrawingApproval;
use App\Models\DrawingRevision;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Services\Construction\ConstructionActivityService;
use App\Services\Construction\TaskManagementService;
use App\Support\Construction\SurveyStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProjectTaskController extends Controller implements HasMiddleware
{
    use ResolvesConstructionActor;
    public static function middleware(): array
    {
        return [
            new Middleware('construction.permission:execution_task.manage', except: ['index', 'show']),
        ];
    }

    public function __construct(
        public readonly TaskManagementService $tasks,
    ) {
    }

    private function mapLegacyStatus(string $in): string
    {
        $map = [
            'draft' => 'planned', 'planned' => 'planned',
            'pending' => 'pending', 'assigned' => 'pending', 'not_started' => 'pending',
            'in_progress' => 'in_progress', 'in-progress' => 'in_progress',
            'active' => 'in_progress', 'progress' => 'in_progress',
            'in_review' => 'review', 'review' => 'review', 'submitted' => 'review',
            'completed' => 'completed', 'approved' => 'completed', 'done' => 'completed',
            'rejected' => 'blocked', 'blocked' => 'blocked', 'on_hold' => 'blocked',
            'cancelled' => 'cancelled', 'canceled' => 'cancelled',
        ];
        return $map[strtolower(trim($in))] ?? 'pending';
    }

    private function parseInputDate(mixed $in): ?\Illuminate\Support\Carbon
    {
        if ($in === null || $in === '') {
            return null;
        }
        if ($in instanceof \DateTimeInterface) {
            return \Illuminate\Support\Carbon::instance($in);
        }
        $s = is_string($in) ? trim($in) : (string) $in;
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) {
            try {
                return \Illuminate\Support\Carbon::createFromFormat('!d-m-Y', $s);
            } catch (\Throwable) {
                // fallthrough to generic parse below
            }
        }
        try {
            return \Illuminate\Support\Carbon::parse($s);
        } catch (\Throwable) {
            return null;
        }
    }

    private function mapEditorToService(Request $request, Project $project): array
    {
        $validated = $request->validate([
            'execution_plan_id' => ['nullable', 'integer', 'exists:construction_execution_plans,id'],
            'parent_task_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'planned_start_date' => ['nullable'],
            'planned_end_date' => ['nullable'],
            'actual_start_date' => ['nullable'],
            'actual_end_date' => ['nullable'],
            'priority' => ['nullable', 'in:low,medium,high,critical'],
            'planned_quantity' => ['nullable', 'numeric', 'min:0'],
            'completed_quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'progress_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'requires_daily_update' => ['nullable', 'boolean'],
            'requires_gps_verification' => ['nullable', 'boolean'],
            'supervisor_member_id' => ['nullable', 'integer', 'exists:members,id'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $plannedStart = $this->parseInputDate($validated['planned_start_date'] ?? null);
        $plannedEnd = $this->parseInputDate($validated['planned_end_date'] ?? null);
        if ($plannedStart && $plannedEnd && $plannedEnd->lessThan($plannedStart)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'planned_end_date' => ['Planned end date must be on or after the planned start date.'],
            ]);
        }
        $actualStart = $this->parseInputDate($validated['actual_start_date'] ?? null);
        $actualEnd = $this->parseInputDate($validated['actual_end_date'] ?? null);
        if ($actualStart && $actualEnd && $actualEnd->lessThan($actualStart)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'actual_end_date' => ['Actual end date must be on or after the actual start date.'],
            ]);
        }

        $parentId = $validated['parent_task_id'] ?? null;
        if ($parentId !== null && $parentId !== '') {
            $parentMatch = Task::query()->where('project_id', $project->id)->find((int) $parentId);
            $parentId = $parentMatch?->id;
        } else {
            $parentId = null;
        }

        $patch = [
            'execution_plan_id' => $validated['execution_plan_id'] ?? null,
            'parent_task_id' => $parentId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => ($actualStart ?? $plannedStart)?->toDateString(),
            'end_date' => ($actualEnd ?? $plannedEnd)?->toDateString(),
            'actual_start_date' => $actualStart?->toDateString(),
            'actual_end_date' => $actualEnd?->toDateString(),
            'planned_start_date' => $plannedStart?->toDateString(),
            'planned_end_date' => $plannedEnd?->toDateString(),
            'priority' => $validated['priority'] ?? 'medium',
            'planned_qty' => $validated['planned_quantity'] ?? null,
            'completed_qty' => $validated['completed_quantity'] ?? null,
            'qty_unit' => $validated['unit'] ?? null,
            'progress_percent' => isset($validated['progress_percent'])
                ? max(0, min(100, (int) round((float) $validated['progress_percent'])))
                : null,
            'requires_gps_verification' => isset($validated['requires_gps_verification'])
                ? (bool) $validated['requires_gps_verification']
                : null,
            'requires_daily_update' => isset($validated['requires_daily_update'])
                ? (bool) $validated['requires_daily_update']
                : null,
            'assigned_supervisor_member_id' => $validated['supervisor_member_id'] ?? null,
            'status' => isset($validated['status']) ? $this->mapLegacyStatus($validated['status']) : null,
            'task_source' => 'admin_created',
        ];

        return array_filter($patch, static fn ($v) => $v !== null, ARRAY_FILTER_USE_BOTH);
    }

    public function index(Request $request, Project $project): Response
    {
        $filters = $request->only(['status','priority','assignee_id','search','sort_by','sort_dir','task_source']);
        $perPage = (int) $request->input('per_page', 25);
        $result = $this->tasks->index($project, $filters, $perPage);

        return Inertia::render('SuperAdmin/Construction/Projects/Show', [
            'project' => $project,
            'tasks' => $result,
            'taskFilters' => (object) $filters,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $actor = $this->constructionActor() ?? $request->user();
        if (!$actor) {
            return Redirect::back()->with('error', 'Unauthenticated actor.');
        }

        try {
            $payload = $this->mapEditorToService($request, $project);
            $payload = $this->tasks->validateCreatePayload($project, $payload + [
                'assignments' => [],
                'checklist_items' => [],
            ]);
            $this->tasks->create($project, $payload, $actor, $request);
            return Redirect::back()->with('success', 'Task created successfully.');
        } catch (ValidationException $e) {
            return Redirect::back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            report($e);
            return Redirect::back()->with('error', config('app.debug') ? $e->getMessage() : 'Failed to create task.')->withInput();
        }
    }

    public function update(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->assertInProject($task, $project);
        $actor = $this->constructionActor() ?? $request->user();
        if (!$actor) {
            return Redirect::back()->with('error', 'Unauthenticated actor.');
        }

        try {
            $patch = $this->mapEditorToService($request, $project);
            $this->tasks->update($task, $patch, $actor, $request);
            return Redirect::back()->with('success', 'Task updated.');
        } catch (ValidationException $e) {
            return Redirect::back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            report($e);
            return Redirect::back()->with('error', config('app.debug') ? $e->getMessage() : 'Task update failed.')->withInput();
        }
    }

    public function destroy(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->assertInProject($task, $project);
        $actor = $this->constructionActor() ?? $request->user();
        if (!$actor) {
            return Redirect::back()->with('error', 'Unauthenticated actor.');
        }

        try {
            $this->tasks->deleteTask($task, $actor, $request);
        } catch (Throwable $e) {
            report($e);
            return Redirect::back()->with('error', config('app.debug') ? $e->getMessage() : 'Failed to delete task.');
        }
        return Redirect::back()->with('success', 'Task deleted.');
    }

    public function replaceAssignments(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->assertInProject($task, $project);
        $actor = $this->constructionActor() ?? $request->user();
        if (!$actor) {
            return Redirect::back()->with('error', 'Unauthenticated actor.');
        }

        $rows = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.member_id' => ['required', 'integer', 'exists:members,id'],
            'assignments.*.assignment_role' => ['nullable', 'in:owner,executor,reviewer,checker,verifier'],
            'assignments.*.is_primary' => ['nullable', 'boolean'],
            'assignments.*.assigned_from' => ['nullable', 'date'],
            'assignments.*.assigned_until' => ['nullable', 'date', 'after_or_equal:assignments.*.assigned_from'],
        ])['assignments'] ?? [];

        try {
            $this->tasks->replaceAssignments($task, $rows, $actor);
            return Redirect::back()->with('success', 'Assignments saved.');
        } catch (Throwable $e) {
            report($e);
            return Redirect::back()->with('error', config('app.debug') ? $e->getMessage() : 'Failed to update assignments.');
        }
    }

    public function saveChecklist(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->assertInProject($task, $project);
        $actor = $this->constructionActor() ?? $request->user();
        if (!$actor) {
            return Redirect::back()->with('error', 'Unauthenticated actor.');
        }

        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['nullable', 'integer', 'exists:task_checklist_items,id'],
            'items.*.item_title' => ['required', 'string', 'max:500'],
            'items.*.day_number' => ['nullable', 'integer', 'min:0', 'max:255'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.is_completed' => ['nullable', 'boolean'],
            'items.*.client_reference' => ['nullable', 'uuid'],
        ])['items'] ?? [];

        try {
            $this->tasks->replaceChecklistItems($task, $items, $actor);
            return Redirect::back()->with('success', 'Checklist saved.');
        } catch (Throwable $e) {
            report($e);
            return Redirect::back()->with('error', config('app.debug') ? $e->getMessage() : 'Failed to save checklist.');
        }
    }

    public function transferTask(Request $request, Project $project, $taskId): RedirectResponse
    {
        $actor = $this->constructionActor() ?? $request->user();

        $validated = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'assignment_role' => ['nullable', 'string', 'max:50'],
            'due_date' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'create_drafting_job' => ['nullable', 'boolean'],
        ]);

        $memberId = (int) $validated['member_id'];
        $roleInput = strtolower(trim((string) ($validated['assignment_role'] ?? 'executor')));
        $dueDate = $this->parseInputDate($validated['due_date'] ?? null);
        $notes = !empty($validated['notes']) ? trim((string) $validated['notes']) : null;
        $shouldCreateDraftingJob = !empty($validated['create_drafting_job']) || in_array($roleInput, ['draft_person', 'drafting', 'drafter', 'draftsman'], true);

        $taskId = (int) $taskId;
        $legacyTask = ExecutionTask::where('project_id', $project->id)->find($taskId);
        $unifiedTask = Task::where('project_id', $project->id)->find($taskId);

        if (!$legacyTask && !$unifiedTask) {
            $legacyTask = ExecutionTask::find($taskId);
            $unifiedTask = Task::find($taskId);
            if (($legacyTask && (int) $legacyTask->project_id !== (int) $project->id) ||
                ($unifiedTask && (int) $unifiedTask->project_id !== (int) $project->id)) {
                return Redirect::back()->with('error', 'Task does not belong to this project.');
            }
        }

        if (!$legacyTask && !$unifiedTask) {
            return Redirect::back()->with('error', 'Task not found in this project.');
        }

        try {
            DB::transaction(function () use (
                $project,
                $legacyTask,
                $unifiedTask,
                $memberId,
                $roleInput,
                $dueDate,
                $notes,
                $shouldCreateDraftingJob,
                $actor,
                $request,
                $taskId
            ) {
                if ($legacyTask) {
                    $legacyTask->supervisor_member_id = $memberId;
                    if ($dueDate) {
                        $legacyTask->planned_end_date = $dueDate->toDateString();
                    }
                    if ($notes) {
                        $legacyTask->description = $legacyTask->description
                            ? $legacyTask->description . "\n\n[Transfer Note: " . $notes . "]"
                            : $notes;
                    }
                    $legacyTask->status = $shouldCreateDraftingJob ? 'drafting_in_progress' : 'in_progress';
                    $legacyTask->save();

                    ExecutionTaskAssignee::where('execution_task_id', $legacyTask->id)
                        ->where('member_id', '!=', $memberId)
                        ->update(['status' => 'inactive']);

                    ExecutionTaskAssignee::updateOrCreate(
                        [
                            'execution_task_id' => $legacyTask->id,
                            'member_id' => $memberId,
                        ],
                        [
                            'project_id' => $project->id,
                            'assignment_role' => in_array($roleInput, ['draft_person', 'surveyor', 'supervisor', 'worker', 'operator'], true) ? $roleInput : 'executor',
                            'is_primary' => true,
                            'assigned_from' => now()->toDateString(),
                            'assigned_to' => $dueDate?->toDateString(),
                            'assigned_by_type' => $actor ? $actor::class : null,
                            'assigned_by_id' => $actor?->getKey(),
                            'status' => 'active',
                        ]
                    );
                }

                if ($unifiedTask) {
                    $unifiedTask->assigned_supervisor_member_id = $memberId;
                    $unifiedTask->member_id = $memberId;
                    if ($dueDate) {
                        $unifiedTask->end_date = $dueDate;
                    }
                    if ($notes) {
                        $unifiedTask->description = $unifiedTask->description
                            ? $unifiedTask->description . "\n\n[Transfer Note: " . $notes . "]"
                            : $notes;
                    }
                    $unifiedTask->status = $shouldCreateDraftingJob ? 'drafting_in_progress' : 'in_progress';
                    $unifiedTask->save();

                    TaskAssignment::where('task_id', $unifiedTask->id)
                        ->where('assigned_to', '!=', $memberId)
                        ->update(['is_transferred' => true]);

                    TaskAssignment::updateOrCreate(
                        [
                            'task_id' => $unifiedTask->id,
                            'assigned_to' => $memberId,
                        ],
                        [
                            'project_id' => $project->id,
                            'assignment_role' => in_array($roleInput, ['owner', 'executor', 'reviewer', 'checker', 'verifier'], true) ? $roleInput : 'executor',
                            'is_primary' => true,
                            'assigned_from' => now(),
                            'assigned_until' => $dueDate,
                            'assigned_by' => (int) ($actor?->getKey() ?? 0),
                            'assigned_by_type' => $actor ? $actor::class : null,
                            'assigned_by_uid' => $actor?->getKey(),
                            'status' => 'active',
                            'is_transferred' => false,
                            'uuid' => (string) Str::uuid(),
                        ]
                    );
                }

                if ($shouldCreateDraftingJob) {
                    $surveyVisit = SurveyVisit::where('task_id', $taskId)->latest()->first()
                        ?? SurveyVisit::where('project_id', $project->id)->latest()->first();

                    if (!$surveyVisit) {
                        $surveyVisit = SurveyVisit::create([
                            'project_id' => $project->id,
                            'task_id' => $unifiedTask?->id ?? $taskId,
                            'execution_task_id' => $legacyTask?->id,
                            'checked_in_by_member_id' => $legacyTask?->supervisor_member_id ?? $unifiedTask?->member_id ?? $memberId,
                            'check_in_at' => now(),
                            'check_out_at' => now(),
                            'status' => 2,
                        ]);
                    }

                    $surveySubmission = null;
                    if ($surveyVisit) {
                        $surveySubmission = SurveySubmission::where('survey_visit_id', $surveyVisit->id)->first();
                    }
                    if (!$surveySubmission) {
                        $surveySubmission = SurveySubmission::where('project_id', $project->id)->latest()->first();
                    }
                    if (!$surveySubmission) {
                        $surveySubmission = SurveySubmission::create([
                            'project_id' => $project->id,
                            'survey_visit_id' => $surveyVisit->id,
                            'task_id' => $unifiedTask?->id ?? $taskId,
                            'execution_task_id' => $legacyTask?->id,
                            'submitted_by_member_id' => $surveyVisit->checked_in_by_member_id ?? $memberId,
                            'submitted_at' => now(),
                            'status' => SurveySubmission::STATUS_APPROVED,
                            'review_notes' => 'Survey auto-approved on transfer to Draft Person.',
                            'reviewed_by_member_id' => $actor instanceof Member ? $actor->id : null,
                            'reviewed_at' => now(),
                        ]);
                    } else {
                        if ((int) $surveySubmission->status !== SurveySubmission::STATUS_APPROVED) {
                            $surveySubmission->status = SurveySubmission::STATUS_APPROVED;
                            $surveySubmission->reviewed_at = now();
                            $surveySubmission->saveQuietly();
                        }
                    }

                    DraftingJob::updateOrCreate(
                        [
                            'project_id' => $project->id,
                            'survey_submission_id' => $surveySubmission->id,
                        ],
                        [
                            'assigned_to_member_id' => $memberId,
                            'assigned_by_type' => $actor ? $actor::class : null,
                            'assigned_by_id' => $actor?->getKey(),
                            'assigned_at' => now(),
                            'due_date' => $dueDate?->toDateString(),
                            'status' => 'queued',
                        ]
                    );

                    $project->update(['current_stage' => 'drafting_in_progress']);
                }

                try {
                    app(ConstructionActivityService::class)->log(
                        module: 'execution_task',
                        action: 'task_transferred',
                        actor: $actor,
                        reference: $legacyTask ?? $unifiedTask,
                        companyId: $project->company_id,
                        projectId: $project->id,
                        meta: [
                            'task_id' => $taskId,
                            'transferred_to_member_id' => $memberId,
                            'role' => $roleInput,
                            'due_date' => $dueDate?->toDateString(),
                            'drafting_job_created' => $shouldCreateDraftingJob,
                        ],
                        request: $request
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            });

            $successMsg = $shouldCreateDraftingJob
                ? 'Task successfully transferred to Draft Person and Drafting Job created.'
                : 'Task successfully assigned / transferred.';

            return Redirect::back()->with('success', $successMsg);
        } catch (\Throwable $e) {
            report($e);
            return Redirect::back()->with('error', config('app.debug') ? $e->getMessage() : 'Failed to transfer task.');
        }
    }

    public function reviewSurvey(Request $request, Project $project, $taskId): RedirectResponse
    {
        $actor = $this->constructionActor() ?? $request->user();

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,revision_requested,rejected'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'transfer_to_member_id' => ['nullable', 'integer', 'exists:members,id'],
            'due_date' => ['nullable'],
            'drafting_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $decision = $validated['decision'];
        $remarks = $validated['remarks'] ?? null;
        $transferMemberId = !empty($validated['transfer_to_member_id']) ? (int) $validated['transfer_to_member_id'] : null;

        $surveyStatusInt = match ($decision) {
            'approved' => SurveySubmission::STATUS_APPROVED,
            'revision_requested' => SurveySubmission::STATUS_REVISION_REQUESTED,
            'rejected' => SurveySubmission::STATUS_REJECTED,
            default => SurveySubmission::STATUS_SUBMITTED,
        };

        $taskId = (int) $taskId;
        $legacyTask = ExecutionTask::find($taskId);
        $unifiedTask = Task::find($taskId);

        if ($legacyTask === null && $unifiedTask !== null) {
            if (!empty($unifiedTask->task_code)) {
                $legacyTask = ExecutionTask::where('task_code', $unifiedTask->task_code)->first();
            }
            if ($legacyTask === null && !empty($unifiedTask->title)) {
                $legacyTask = ExecutionTask::where('project_id', $project->id)->where('title', $unifiedTask->title)->first();
            }
        }
        if ($unifiedTask === null && $legacyTask !== null) {
            if (!empty($legacyTask->task_code)) {
                $unifiedTask = Task::where('task_code', $legacyTask->task_code)->first();
            }
            if ($unifiedTask === null && !empty($legacyTask->title)) {
                $unifiedTask = Task::where('project_id', $project->id)->where('title', $legacyTask->title)->first();
            }
        }

        $taskIds = array_values(array_unique(array_filter([$taskId, $legacyTask?->id, $unifiedTask?->id])));
        $hasExecCol = \Illuminate\Support\Facades\Schema::hasTable('construction_survey_visits')
            && \Illuminate\Support\Facades\Schema::hasColumn('construction_survey_visits', 'execution_task_id');

        $visits = SurveyVisit::where(function ($q) use ($taskIds, $project, $hasExecCol) {
            $q->whereIn('task_id', $taskIds);
            if ($hasExecCol) {
                $q->orWhereIn('execution_task_id', $taskIds);
            }
            if ($project) {
                $q->orWhere(fn ($sub) => $sub->where('project_id', $project->id)->whereIn('task_id', $taskIds));
            }
        })->get();

        if ($visits->isEmpty()) {
            $visits = SurveyVisit::where('project_id', $project->id)->latest()->take(1)->get();
        }

        $now = now();
        $nowFormatted = $now->setTimezone('Asia/Kolkata')->format('d M Y · h:i A');

        foreach ($visits as $visit) {
            $visit->status = match ($decision) {
                'approved' => SurveyVisit::STATUS_APPROVED,
                'revision_requested' => SurveyStatus::REVISION_REQUESTED,
                'rejected' => SurveyVisit::STATUS_REJECTED,
                default => SurveyVisit::STATUS_SUBMITTED,
            };

            // Update visit notes with structured revision history
            $visitNotes = is_array($visit->notes) ? $visit->notes : (json_decode($visit->notes ?? '', true) ?: []);
            if ($decision === 'revision_requested') {
                $history = $visitNotes['revision_history'] ?? [];
                $history[] = [
                    'type' => 'revision_requested',
                    'sender_role' => 'manager',
                    'sender_name' => $actor instanceof Member ? $actor->name : 'Manager',
                    'sender_id' => $actor instanceof Member ? $actor->id : null,
                    'message' => $remarks,
                    'created_at' => $now->toIso8601String(),
                    'created_at_formatted' => $nowFormatted,
                ];
                $visitNotes['revision_request'] = [
                    'status' => 'revision_requested',
                    'remarks' => $remarks,
                    'requested_by_id' => $actor instanceof Member ? $actor->id : null,
                    'requested_by_name' => $actor instanceof Member ? $actor->name : 'Manager',
                    'requested_at' => $now->toIso8601String(),
                    'requested_at_formatted' => $nowFormatted,
                ];
                $visitNotes['revision_history'] = $history;
                unset($visitNotes['member_reply']);
                $visit->notes = json_encode($visitNotes, JSON_UNESCAPED_SLASHES);
            }
            $visit->saveQuietly();

            $sub = SurveySubmission::where('survey_visit_id', $visit->id)->first();
            if ($sub) {
                $sub->status = $surveyStatusInt;
                $sub->review_notes = $remarks;
                $sub->reviewed_by_member_id = $actor instanceof Member ? $actor->id : null;
                $sub->reviewed_at = $now;
                if ($decision === 'revision_requested') {
                    $sub->member_reply = null;
                    $sub->member_replied_at = null;
                }
                $sub->save();
            } else {
                SurveySubmission::create([
                    'project_id' => $project->id,
                    'survey_visit_id' => $visit->id,
                    'task_id' => $legacyTask?->id ?? $unifiedTask?->id ?? $taskId,
                    'execution_task_id' => $legacyTask?->id,
                    'submitted_by_member_id' => $visit->checked_in_by_member_id,
                    'submitted_at' => $visit->check_out_at ?? $visit->created_at ?? $now,
                    'status' => $surveyStatusInt,
                    'review_notes' => $remarks,
                    'reviewed_by_member_id' => $actor instanceof Member ? $actor->id : null,
                    'reviewed_at' => $now,
                ]);
            }
        }

        if ($decision === 'approved') {
            if ($legacyTask) {
                $legacyTask->status = $transferMemberId ? 'drafting_in_progress' : 'survey_approved';
                $legacyTask->save();
            }
            if ($unifiedTask) {
                $unifiedTask->status = 'review';
                $unifiedTask->client_review_status = 'approved';
                $unifiedTask->save();
            }

            if ($transferMemberId) {
                $fakeRequest = new Request([
                    'member_id' => $transferMemberId,
                    'assignment_role' => 'draft_person',
                    'due_date' => $validated['due_date'] ?? null,
                    'notes' => $validated['drafting_notes'] ?? $remarks,
                    'create_drafting_job' => true,
                ]);
                return $this->transferTask($fakeRequest, $project, $taskId);
            }
        } elseif ($decision === 'revision_requested') {
            if ($legacyTask) {
                $legacyTask->status = 'in_progress';
                $legacyTask->save();
            }
            if ($unifiedTask) {
                $unifiedTask->status = 'in_progress';
                $unifiedTask->client_review_status = 'revision_requested';
                $unifiedTask->save();
            }
            try {
                \App\Models\TaskComment::create([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'task_id' => $unifiedTask?->id ?? $legacyTask?->id ?? $taskId,
                    'project_id' => $project->id,
                    'commented_by' => $actor instanceof Member ? $actor->id : null,
                    'comment' => "Manager requested survey revision: " . $remarks,
                    'kind' => 'revision_requested',
                    'from_status' => 'review',
                    'to_status' => 'in_progress',
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        } elseif ($decision === 'rejected') {
            if ($legacyTask) {
                $legacyTask->status = 'blocked';
                $legacyTask->save();
            }
            if ($unifiedTask) {
                $unifiedTask->status = 'blocked';
                $unifiedTask->client_review_status = 'rejected';
                $unifiedTask->save();
            }
        }

        $msg = match ($decision) {
            'approved' => 'Survey report approved successfully.',
            'revision_requested' => 'Survey sent back for revision with your remarks.',
            'rejected' => 'Survey report marked as rejected.',
        };

        return Redirect::back()->with('success', $msg);
    }

    public function submitDraftDrawing(Request $request, Project $project): RedirectResponse
    {
        $actor = $this->constructionActor() ?? $request->user();

        $validated = $request->validate([
            'drafting_job_id' => ['nullable', 'integer'],
            'drawing_type' => ['required', 'string', 'max:255'],
            'drawing_no' => ['required', 'string', 'max:100'],
            'revision_no' => ['nullable', 'integer'],
            'changes_made' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'dwg_file' => ['nullable', 'file', 'max:51200'],
            'pdf_file' => ['nullable', 'file', 'max:51200'],
        ]);

        $draftingJob = null;
        if (!empty($validated['drafting_job_id'])) {
            $draftingJob = DraftingJob::where('project_id', $project->id)->find($validated['drafting_job_id']);
        }
        if (!$draftingJob) {
            $draftingJob = DraftingJob::where('project_id', $project->id)
                ->when($actor instanceof Member, fn ($q) => $q->where('assigned_to_member_id', $actor->id))
                ->latest()
                ->first();
        }
        if (!$draftingJob) {
            $draftingJob = DraftingJob::firstOrCreate(
                ['project_id' => $project->id],
                [
                    'assigned_to_member_id' => $actor instanceof Member ? $actor->id : null,
                    'assigned_at' => now(),
                    'status' => 'queued',
                ]
            );
        }

        $dwgDoc = null;
        if ($request->hasFile('dwg_file')) {
            $dwgDoc = app(\App\Services\Construction\ConstructionDocumentService::class)->storeDocument(
                documentable: $draftingJob,
                actor: $actor,
                folder: 'construction/drawings/dwg',
                file: $request->file('dwg_file'),
                companyId: $project->company_id,
                projectId: $project->id
            );
        }

        $pdfDoc = null;
        if ($request->hasFile('pdf_file')) {
            $pdfDoc = app(\App\Services\Construction\ConstructionDocumentService::class)->storeDocument(
                documentable: $draftingJob,
                actor: $actor,
                folder: 'construction/drawings/pdf',
                file: $request->file('pdf_file'),
                companyId: $project->company_id,
                projectId: $project->id
            );
        }

        $revNo = !empty($validated['revision_no'])
            ? (int) $validated['revision_no']
            : (int) $draftingJob->drawingRevisions()->max('revision_no') + 1;

        $revision = DrawingRevision::create([
            'project_id' => $project->id,
            'drafting_job_id' => $draftingJob->id,
            'revision_no' => $revNo,
            'drawing_type' => $validated['drawing_type'],
            'drawing_no' => $validated['drawing_no'],
            'dwg_document_id' => $dwgDoc?->id,
            'pdf_document_id' => $pdfDoc?->id,
            'changes_made' => $validated['changes_made'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'uploaded_by_member_id' => $actor instanceof Member ? $actor->id : null,
            'uploaded_at' => now(),
            'status' => 'submitted',
        ]);

        DrawingApproval::create([
            'project_id' => $project->id,
            'drawing_revision_id' => $revision->id,
            'requested_by_type' => $actor ? $actor::class : null,
            'requested_by_id' => $actor?->getKey(),
            'requested_at' => now(),
            'decision' => 'pending',
        ]);

        $draftingJob->update([
            'status' => 'submitted',
        ]);

        $project->update([
            'current_stage' => 'drawing_approval_pending',
        ]);

        return Redirect::back()->with('success', 'CAD Drawing submitted successfully for Approval.');
    }

    public function reviewDraftingJob(Request $request, Project $project, DraftingJob $draftingJob): RedirectResponse
    {
        $actor = $this->constructionActor() ?? $request->user();

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,revision_requested,rejected'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $decision = $validated['decision'];
        $remarks = $validated['remarks'] ?? null;

        if ($decision === 'approved') {
            $draftingJob->update([
                'status' => 'approved',
                'rejection_reason' => null,
            ]);
            $latestRev = $draftingJob->drawingRevisions()->latest()->first();
            if ($latestRev) {
                $latestRev->update(['status' => 'approved', 'is_locked' => true]);
                DrawingApproval::where('drawing_revision_id', $latestRev->id)
                    ->where('decision', 'pending')
                    ->update([
                        'decision' => 'approved',
                        'approved_at' => now(),
                        'approved_by_type' => $actor ? $actor::class : null,
                        'approved_by_id' => $actor?->getKey(),
                        'remarks' => $remarks,
                    ]);
            }

            // Advance associated tasks to completed / ready
            ExecutionTask::where('project_id', $project->id)
                ->where('status', 'drafting_in_progress')
                ->update(['status' => 'completed']);

            Task::where('project_id', $project->id)
                ->where('status', 'drafting_in_progress')
                ->update(['status' => 'completed']);

            // Advance project stage to Construction Execution
            $project->update(['current_stage' => 'ready_for_construction']);
        } else {
            $draftingJob->update([
                'status' => 'revision_requested',
                'rejection_reason' => $remarks,
                'rejected_at' => now(),
                'rejected_by_member_id' => $actor instanceof Member ? $actor->id : null,
                'rejection_count' => (int) $draftingJob->rejection_count + 1,
            ]);
            $latestRev = $draftingJob->drawingRevisions()->latest()->first();
            if ($latestRev) {
                $latestRev->update(['status' => 'revision_requested']);
                DrawingApproval::where('drawing_revision_id', $latestRev->id)
                    ->where('decision', 'pending')
                    ->update([
                        'decision' => 'revision_requested',
                        'approved_at' => now(),
                        'approved_by_type' => $actor ? $actor::class : null,
                        'approved_by_id' => $actor?->getKey(),
                        'remarks' => $remarks,
                    ]);
            }
            $project->update(['current_stage' => 'drafting_in_progress']);
        }

        return Redirect::back()->with('success', 'Drafting review updated: ' . ucfirst($decision));
    }

    private function assertInProject(Task $task, Project $project): void
    {
        if ((int) $task->project_id !== (int) $project->id) {
            abort(403, 'Task does not belong to this project.');
        }
    }
}
