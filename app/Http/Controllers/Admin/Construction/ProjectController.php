<?php

namespace App\Http\Controllers\Admin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\ConstructionActivityLog;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\Member;
use App\Models\TaskChecklist;
use App\Services\Construction\ConstructionActivityService;
use App\Services\Construction\SurveyDataService;
use App\Services\Construction\TaskManagementService;
use App\Models\ExecutionTask;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = ProjectTeamMember::where('member_id', $actor?->getKey())->pluck('project_id');

        return Inertia::render('Admin/Construction/Projects/Index', [
            'projects' => Project::with(['company', 'client', 'latestBudget'])
                ->whereIn('id', $projectIds)
                ->latest()
                ->get(),
            'companies' => Project::with(['company'])
                ->whereIn('id', $projectIds)
                ->get()
                ->pluck('company')
                ->filter()
                ->unique('id')
                ->values()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'clients' => Project::with(['client'])
                ->whereIn('id', $projectIds)
                ->get()
                ->pluck('client')
                ->filter()
                ->unique('id')
                ->values()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'company_id' => $c->company_id]),
        ]);
    }

    public function show(Project $project, SurveyDataService $surveyData): Response
    {
        $actor = $this->constructionActor();
        $memberId = $actor?->getKey();
        $assigned = ProjectTeamMember::where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->exists();
        if (!$assigned && $actor) {
            $isAdmin = method_exists($actor, 'isAdmin') ? $actor->isAdmin() : false;
            if (!$isAdmin) {
                abort(403, 'You are not assigned to this project.');
            }
        }

        $project->load([
            'company',
            'client',
            'budgets' => fn ($query) => $query->latest('version_no'),
            'teamMembers.member',
            'teamMembers.role',
            'surveyPlans.planMembers.member',
            'surveyPlans.visits.checkedInBy',
            'surveyVisits.checkedInBy',
            'surveyPlans.visits.entries.capturedBy',
            'surveyPlans.visits.measurements.capturedBy',
            'surveyPlans.visits.submission.submittedBy',
            'surveyPlans.visits.submission.reviewedBy',
            'surveyPlans.checklists.completedBy',
            'surveySubmissions.submittedBy',
            'surveySubmissions.reviewedBy',
            'surveySubmissions.surveyVisit.checkedInBy',
            'draftingJobs.assignedTo',
            'draftingJobs.drawingRevisions.uploadedBy',
            'draftingJobs.drawingRevisions.dwgDocument',
            'draftingJobs.drawingRevisions.pdfDocument',
            'draftingJobs.drawingRevisions.approvals.approvedBy',
            'drawingApprovals.drawingRevision',
            'executionTasks.supervisor',
            'executionTasks.assignees.member',
            'executionTasks.checklists.completedBy',
            'executionTasks.progressReports.submittedBy',
            'executionTasks.progressReports.reviewedBy',
            'tasks.checklistItems.completedBy',
            'tasks.supervisor',
            'tasks.assignedMembers.member',
            'dailyProgressReports.submittedBy',
            'dailyProgressReports.reviewedBy',
            'dailyProgressReports.items',
            'attendanceRecords.member',
            'attendanceRecords.checkedInBy',
            'attendanceRecords.checkedOutBy',
            'materialStocks.material',
            'materialIssues.issuedBy',
            'materialIssues.material',
            'purchaseRequests.createdBy',
            'purchaseOrders.createdBy',
            'purchaseOrders.vendor',
            'materialReceipts.receivedBy',
            'materialReceipts.material',
            'clientInvoices',
            'clientPayments',
            'handovers.items',
        ]);

        return Inertia::render('Admin/Construction/Projects/Show', [
            'project' => $project,
            'activityLog' => ConstructionActivityLog::with('actor')
                ->where('project_id', $project->id)
                ->latest('created_at')
                ->take(15)
                ->get(),
            'workflowSummary' => [
                'total_survey_days' => $surveyData->getTotalSurveyDays(
                    $project->surveyPlans->first(),
                    $project
                ),
                'default_shift' => $surveyData->getShiftConfig(),
                'default_checklist_items' => $surveyData->getDefaultChecklistItems(),
                'site_address' => $surveyData->resolveSiteAddress(
                    $project->surveyPlans->first(),
                    $project
                ),
                'site_coordinates' => $surveyData->resolveSiteCoordinates(
                    $project->surveyPlans->first(),
                    $project
                ),
                'task_counts' => [
                    'total' => $project->executionTasks->count() + $project->tasks->count(),
                    'completed' => $project->executionTasks
                            ->where(fn ($t) => $t->status === 'completed')
                            ->count()
                        + $project->tasks
                            ->where(fn ($t) => $t->status === 'completed')
                            ->count(),
                    'in_progress' => $project->executionTasks
                            ->where(fn ($t) => $t->status === 'in_progress')
                            ->count()
                        + $project->tasks
                            ->where(fn ($t) => $t->status === 'in_progress')
                            ->count(),
                    'pending' => $project->executionTasks
                            ->where(fn ($t) => !in_array($t->status, ['completed', 'in_progress']))
                            ->count()
                        + $project->tasks
                            ->where(fn ($t) => !in_array($t->status, ['completed', 'in_progress']))
                            ->count(),
                ],
                'checklist_counts' => [
                    'total' => $project->executionTasks->flatMap(fn ($t) => $t->checklists ?? collect())->count()
                        + $project->surveyPlans->flatMap(fn ($s) => $s->checklists ?? collect())->count()
                        + $project->tasks->flatMap(fn ($t) => $t->checklistItems ?? collect())->count(),
                    'completed' => $project->executionTasks
                            ->flatMap(fn ($t) => $t->checklists ?? collect())
                            ->where('is_completed', true)
                            ->count()
                        + $project->surveyPlans
                            ->flatMap(fn ($s) => $s->checklists ?? collect())
                            ->where('is_completed', true)
                            ->count()
                        + $project->tasks
                            ->flatMap(fn ($t) => $t->checklistItems ?? collect())
                            ->where('is_completed', true)
                            ->count(),
                ],
            ],
        ]);
    }

    private function resolveUnifiedTask(Project $project, ExecutionTask $et): ?Task
    {
        $taskCode = (string) ($et->task_code ?? sprintf('ET-%s-%06d', (int) $project->id, (int) $et->id));
        return Task::query()
            ->where('project_id', $project->id)
            ->where(fn ($q) => $q->where('task_code', $taskCode)
                ->orWhere('task_code', sprintf('ET-%s-%06d', (int) $project->id, (int) $et->id)))
            ->first();
    }

    private function resolveUnifiedParentTaskId(Project $project, mixed $legacyParentId): ?int
    {
        if (empty($legacyParentId)) {
            return null;
        }
        $legacyId = (int) $legacyParentId;
        $alreadyUnified = Task::query()->find($legacyId);
        if ($alreadyUnified && (int) $alreadyUnified->project_id === (int) $project->id) {
            return $legacyId;
        }
        $legacyParent = ExecutionTask::query()->find($legacyId);
        if (!$legacyParent || (int) $legacyParent->project_id !== (int) $project->id) {
            return null;
        }
        $unified = $this->resolveUnifiedTask($project, $legacyParent);
        return $unified?->id;
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

    public function storeTask(
        Project $project,
        Request $request,
        TaskManagementService $tasks
    ) {
        $this->authorizeProjectWrite($project);
        $actor = $this->constructionActor();

        try {
            $validated = $request->validate([
                'execution_plan_id' => ['nullable', 'integer', 'exists:construction_execution_plans,id'],
                'parent_task_id' => ['nullable', 'integer'],
                'title' => ['required', 'string', 'max:500'],
                'description' => ['nullable', 'string', 'max:10000'],
                'planned_start_date' => ['nullable', 'date'],
                'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
                'actual_start_date' => ['nullable', 'date'],
                'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
                'priority' => ['nullable', 'in:low,medium,high,critical'],
                'planned_quantity' => ['nullable', 'numeric', 'min:0'],
                'unit' => ['nullable', 'string', 'max:50'],
                'requires_daily_update' => ['nullable', 'boolean'],
                'requires_gps_verification' => ['nullable', 'boolean'],
                'supervisor_member_id' => ['nullable', 'integer', 'exists:members,id'],
                'status' => ['nullable', 'string', 'max:50'],
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $payload = [
            'execution_plan_id' => $validated['execution_plan_id'] ?? null,
            'parent_task_id' => $this->resolveUnifiedParentTaskId($project, $validated['parent_task_id'] ?? null),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['actual_start_date'] ?? $validated['planned_start_date'] ?? null,
            'end_date' => $validated['actual_end_date'] ?? $validated['planned_end_date'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'planned_qty' => $validated['planned_quantity'] ?? null,
            'qty_unit' => $validated['unit'] ?? null,
            'requires_gps_verification' => (bool) ($validated['requires_gps_verification'] ?? false),
            'assigned_supervisor_member_id' => $validated['supervisor_member_id'] ?? null,
            'status' => isset($validated['status']) ? $this->mapLegacyStatus($validated['status']) : 'pending',
            'task_source' => 'admin_created',
            'checklist_items' => [],
            'assignments' => [],
        ];

        try {
            $task = $tasks->create($project, $payload, $actor, $request);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to create task. ' . $e->getMessage())->withInput();
        }

        return back()->with('success', "Task {$task->task_code} created successfully.");
    }

    public function updateTask(
        Project $project,
        ExecutionTask $task,
        Request $request,
        TaskManagementService $tasks
    ) {
        $this->authorizeProjectWrite($project);
        if ((int) $task->project_id !== (int) $project->id) {
            return back()->with('error', 'The selected task does not belong to this project.');
        }

        $actor = $this->constructionActor();
        $unified = $this->resolveUnifiedTask($project, $task);
        if (!$unified) {
            return back()->with('error', 'Backfilled task record not found. Re-run migrations or create a new task.');
        }

        try {
            $validated = $request->validate([
                'execution_plan_id' => ['nullable', 'integer', 'exists:construction_execution_plans,id'],
                'parent_task_id' => ['nullable', 'integer'],
                'title' => ['required', 'string', 'max:500'],
                'description' => ['nullable', 'string', 'max:10000'],
                'planned_start_date' => ['nullable', 'date'],
                'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
                'actual_start_date' => ['nullable', 'date'],
                'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
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
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $patch = [
            'execution_plan_id' => $validated['execution_plan_id'] ?? null,
            'parent_task_id' => $this->resolveUnifiedParentTaskId($project, $validated['parent_task_id'] ?? null),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['actual_start_date'] ?? $validated['planned_start_date'] ?? $unified->start_date ?? null,
            'end_date' => $validated['actual_end_date'] ?? $validated['planned_end_date'] ?? $unified->end_date ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'planned_qty' => $validated['planned_quantity'] ?? null,
            'completed_qty' => $validated['completed_quantity'] ?? null,
            'qty_unit' => $validated['unit'] ?? null,
            'progress_percent' => isset($validated['progress_percent']) ? max(0, min(100, (int) round((float) $validated['progress_percent']))) : null,
            'requires_gps_verification' => isset($validated['requires_gps_verification']) ? (bool) $validated['requires_gps_verification'] : null,
            'assigned_supervisor_member_id' => $validated['supervisor_member_id'] ?? null,
            'status' => isset($validated['status']) ? $this->mapLegacyStatus($validated['status']) : null,
        ];
        $patch = array_filter($patch, static fn ($v) => $v !== null, ARRAY_FILTER_USE_BOTH);

        try {
            $updated = $tasks->update($unified, $patch, $actor, $request);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to update task. ' . $e->getMessage())->withInput();
        }

        return back()->with('success', "Task {$updated->task_code} updated successfully.");
    }

    public function destroyTask(
        Project $project,
        ExecutionTask $task,
        TaskManagementService $tasks
    ) {
        $this->authorizeProjectWrite($project);
        if ((int) $task->project_id !== (int) $project->id) {
            return back()->with('error', 'The selected task does not belong to this project.');
        }

        $actor = $this->constructionActor();
        $unified = $this->resolveUnifiedTask($project, $task);
        $code = $unified?->task_code ?? ($task->task_code ?? (string) $task->id);

        if (!$unified) {
            try {
                DB::transaction(function () use ($task) {
                    TaskChecklist::where('execution_task_id', $task->id)->delete();
                    \App\Models\ExecutionTaskAssignee::where('execution_task_id', $task->id)->delete();
                    \App\Models\DailyProgressReport::where('execution_task_id', $task->id)->update(['execution_task_id' => null]);
                    \App\Models\ExecutionTask::where('parent_task_id', $task->id)->update(['parent_task_id' => null]);
                    $task->delete();
                });
            } catch (\Throwable $e) {
                report($e);
                return back()->with('error', 'Failed to delete task. ' . $e->getMessage());
            }
            return back()->with('success', "Task {$code} deleted successfully.");
        }

        try {
            $tasks->deleteTask($unified, $actor, $request);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to delete task. ' . $e->getMessage());
        }

        return back()->with('success', "Task {$code} deleted successfully.");
    }

    public function storeChecklistItem(
        Project $project,
        Request $request,
        ConstructionActivityService $activityService,
        SurveyDataService $surveyData
    ) {
        $this->authorizeProjectWrite($project);
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'scope' => ['required', 'in:execution_task,survey_plan,project,unified_task'],
            'execution_task_id' => [
                'nullable',
                'integer',
                'exists:construction_execution_tasks,id',
            ],
            'survey_plan_id' => [
                'nullable',
                'integer',
                'exists:construction_survey_plans,id',
            ],
            'unified_task_id' => [
                'nullable',
                'integer',
                'exists:tasks,id',
            ],
            'day_number' => ['required', 'integer', 'min:1', 'max:366'],
            'item_title' => ['required', 'string', 'max:255'],
            'assign_hours' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:pending,in_progress,completed'],
            'is_completed' => ['nullable', 'boolean'],
            'image_1' => ['nullable', 'image', 'max:10240'],
            'image_2' => ['nullable', 'image', 'max:10240'],
        ]);

        if (
            $validated['scope'] === 'execution_task'
            && isset($validated['execution_task_id'])
            && \App\Models\ExecutionTask::where('id', $validated['execution_task_id'])->value('project_id') !== $project->id
        ) {
            return back()->with('error', 'The selected execution task does not belong to this project.');
        }

        if (
            $validated['scope'] === 'survey_plan'
            && isset($validated['survey_plan_id'])
            && \App\Models\SurveyPlan::where('id', $validated['survey_plan_id'])->value('project_id') !== $project->id
        ) {
            return back()->with('error', 'The selected survey plan does not belong to this project.');
        }

        try {
            $taskId = $validated['scope'] === 'execution_task' ? $validated['execution_task_id'] : null;
            $planId = $validated['scope'] === 'survey_plan' ? $validated['survey_plan_id'] : null;

            if ($validated['scope'] === 'project') {
                $firstPlan = $project->surveyPlans->first();
                $planId = $firstPlan?->id;
            }

            $imageUrl1 = null;
            if ($request->hasFile('image_1')) {
                $path = $request->file('image_1')->store('checklists', 'public');
                $imageUrl1 = '/storage/' . $path;
            }

            $imageUrl2 = null;
            if ($request->hasFile('image_2')) {
                $path = $request->file('image_2')->store('checklists', 'public');
                $imageUrl2 = '/storage/' . $path;
            }

            $isDone = !empty($validated['is_completed']) || ($validated['status'] ?? '') === 'completed';
            $statusVal = $validated['status'] ?? ($isDone ? 'completed' : 'pending');

            $checklist = TaskChecklist::create([
                'execution_task_id' => $taskId,
                'survey_plan_id' => $planId,
                'day_number' => $validated['day_number'],
                'item_title' => $validated['item_title'],
                'assign_hours' => $validated['assign_hours'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => $statusVal,
                'image_url_1' => $imageUrl1,
                'image_url_2' => $imageUrl2,
                'is_completed' => $isDone,
                'completed_by_member_id' => $isDone ? $actor?->getKey() : null,
                'completed_at' => $isDone ? now() : null,
            ]);

            if ($validated['scope'] === 'unified_task' && !empty($validated['unified_task_id'])) {
                try {
                    \App\Models\TaskChecklistItem::create([
                        'task_id' => $validated['unified_task_id'],
                        'project_id' => $project->id,
                        'day_number' => $validated['day_number'],
                        'item_title' => $validated['item_title'],
                        'assign_hours' => $validated['assign_hours'] ?? null,
                        'notes' => $validated['notes'] ?? null,
                        'status' => $statusVal,
                        'image_url_1' => $imageUrl1,
                        'image_url_2' => $imageUrl2,
                        'is_completed' => $isDone,
                        'source' => 'admin_custom',
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            if (!$taskId && !$planId) {
                try {
                    $surveyData->ensureDefaultTaskChecklists(
                        $project->executionTasks->first() ?? new \App\Models\ExecutionTask([
                            'project_id' => $project->id,
                            'title' => $project->name,
                        ]),
                        (int) $validated['day_number']
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Failed to create checklist item. ' . $e->getMessage());
        }

        $activityService->log(
            module: 'task_checklist',
            action: 'created',
            actor: $actor,
            reference: $checklist,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'day_number' => $checklist->day_number,
                'scope' => $validated['scope'],
            ],
            request: $request
        );

        return back()->with('success', "Checklist item added for Day {$checklist->day_number}.");
    }

    public function updateChecklistItem(
        Project $project,
        TaskChecklist $checklist,
        Request $request,
        ConstructionActivityService $activityService
    ) {
        $this->authorizeProjectWrite($project);
        $actor = $this->constructionActor();

        if (!$this->checklistBelongsToProject($checklist, $project)) {
            return back()->with('error', 'The selected checklist item does not belong to this project.');
        }

        $validated = $request->validate([
            'day_number' => ['nullable', 'integer', 'min:1', 'max:366'],
            'item_title' => ['nullable', 'string', 'max:255'],
            'assign_hours' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:pending,in_progress,completed'],
            'is_completed' => ['nullable', 'boolean'],
            'image_1' => ['nullable', 'image', 'max:10240'],
            'image_2' => ['nullable', 'image', 'max:10240'],
            'remove_image_1' => ['nullable', 'boolean'],
            'remove_image_2' => ['nullable', 'boolean'],
        ]);

        try {
            $attributes = [];
            if (isset($validated['day_number'])) {
                $attributes['day_number'] = $validated['day_number'];
            }
            if (array_key_exists('item_title', $validated)) {
                $attributes['item_title'] = $validated['item_title'];
            }
            if (array_key_exists('assign_hours', $validated)) {
                $attributes['assign_hours'] = $validated['assign_hours'];
            }
            if (array_key_exists('notes', $validated)) {
                $attributes['notes'] = $validated['notes'];
            }
            if (array_key_exists('status', $validated)) {
                $attributes['status'] = $validated['status'];
                if ($validated['status'] === 'completed') {
                    $attributes['is_completed'] = true;
                    $attributes['completed_at'] = $checklist->completed_at ?? now();
                    $attributes['completed_by_member_id'] = $checklist->completed_by_member_id ?? $actor?->getKey();
                }
            }
            if (array_key_exists('is_completed', $validated)) {
                $attributes['is_completed'] = (bool) $validated['is_completed'];
                if ((bool) $validated['is_completed']) {
                    $attributes['status'] = 'completed';
                    $attributes['completed_at'] = $checklist->completed_at ?? now();
                    $attributes['completed_by_member_id'] = $checklist->completed_by_member_id ?? $actor?->getKey();
                } else {
                    $attributes['status'] = 'pending';
                    $attributes['completed_at'] = null;
                    $attributes['completed_by_member_id'] = null;
                }
            }

            if ($request->hasFile('image_1')) {
                $path = $request->file('image_1')->store('checklists', 'public');
                $attributes['image_url_1'] = '/storage/' . $path;
            } elseif ($request->boolean('remove_image_1')) {
                $attributes['image_url_1'] = null;
            }

            if ($request->hasFile('image_2')) {
                $path = $request->file('image_2')->store('checklists', 'public');
                $attributes['image_url_2'] = '/storage/' . $path;
            } elseif ($request->boolean('remove_image_2')) {
                $attributes['image_url_2'] = null;
            }

            $checklist->update($attributes);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Failed to update checklist item. ' . $e->getMessage());
        }

        $activityService->log(
            module: 'task_checklist',
            action: 'updated',
            actor: $actor,
            reference: $checklist,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'day_number' => $checklist->day_number,
                'is_completed' => $checklist->is_completed,
            ],
            request: $request
        );

        return back()->with(
            'success',
            'Checklist item updated successfully.'
        );
    }

    public function toggleChecklistItem(
        Project $project,
        TaskChecklist $checklist,
        ConstructionActivityService $activityService
    ) {
        $this->authorizeProjectWrite($project);
        $actor = $this->constructionActor();

        if (!$this->checklistBelongsToProject($checklist, $project)) {
            return back()->with(
                'error',
                'The selected checklist item does not belong to this project.'
            );
        }

        $nextCompleted = !$checklist->is_completed;
        try {
            $checklist->update([
                'is_completed' => $nextCompleted,
                'completed_by_member_id' => $nextCompleted ? $actor?->getKey() : null,
                'completed_at' => $nextCompleted ? now() : null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to toggle checklist item. ' . $e->getMessage()
            );
        }

        $activityService->log(
            module: 'task_checklist',
            action: $nextCompleted ? 'completed' : 'reopened',
            actor: $actor,
            reference: $checklist,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['day_number' => $checklist->day_number],
            request: request()
        );

        return back()->with(
            'success',
            $nextCompleted ? 'Checklist item marked complete.' : 'Checklist item reopened.'
        );
    }

    public function destroyChecklistItem(
        Project $project,
        TaskChecklist $checklist,
        ConstructionActivityService $activityService
    ) {
        $this->authorizeProjectWrite($project);
        $actor = $this->constructionActor();

        if (!$this->checklistBelongsToProject($checklist, $project)) {
            return back()->with(
                'error',
                'The selected checklist item does not belong to this project.'
            );
        }

        $title = $checklist->item_title;
        $dayNumber = $checklist->day_number;

        try {
            $checklist->delete();
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to delete checklist item. ' . $e->getMessage()
            );
        }

        $activityService->log(
            module: 'task_checklist',
            action: 'deleted',
            actor: $actor,
            reference: null,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'item_title' => $title,
                'day_number' => $dayNumber,
            ],
            request: request()
        );

        return back()->with(
            'success',
            "Checklist item removed from Day {$dayNumber}."
        );
    }

    public function seedDefaultChecklists(
        Project $project,
        Request $request,
        ConstructionActivityService $activityService,
        SurveyDataService $surveyData
    ) {
        $this->authorizeProjectWrite($project);
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'day_number' => ['required', 'integer', 'min:1', 'max:366'],
            'target_scope' => ['required', 'in:default,survey_plan,execution_task,all'],
            'survey_plan_id' => ['nullable', 'integer', 'exists:construction_survey_plans,id'],
            'execution_task_id' => ['nullable', 'integer', 'exists:construction_execution_tasks,id'],
        ]);

        $items = $surveyData->getDefaultChecklistItems();
        if (empty($items)) {
            return back()->with(
                'error',
                'No default checklist items are configured in the survey defaults.'
            );
        }

        try {
            $targets = [];
            $scope = $validated['target_scope'];

            if ($scope === 'default' || $scope === 'all') {
                $plan = isset($validated['survey_plan_id'])
                    ? \App\Models\SurveyPlan::find($validated['survey_plan_id'])
                    : $project->surveyPlans->first();
                if ($plan && (int) $plan->project_id === (int) $project->id) {
                    $targets[] = [
                        'label' => "Survey Plan {$plan->survey_code}",
                        'survey_plan_id' => $plan->id,
                        'execution_task_id' => null,
                    ];
                }
                $task = isset($validated['execution_task_id'])
                    ? \App\Models\ExecutionTask::find($validated['execution_task_id'])
                    : $project->executionTasks->first();
                if ($task && (int) $task->project_id === (int) $project->id) {
                    $surveyData->ensureDefaultTaskChecklists($task, (int) $validated['day_number']);
                }
            }

            if ($scope === 'survey_plan' || $scope === 'all') {
                $plans = isset($validated['survey_plan_id'])
                    ? \App\Models\SurveyPlan::where('id', $validated['survey_plan_id'])->where('project_id', $project->id)->get()
                    : $project->surveyPlans;
                foreach ($plans as $plan) {
                    $targets[] = [
                        'label' => "Survey Plan {$plan->survey_code}",
                        'survey_plan_id' => $plan->id,
                        'execution_task_id' => null,
                    ];
                }
            }

            if ($scope === 'execution_task' || $scope === 'all') {
                $tasks = isset($validated['execution_task_id'])
                    ? \App\Models\ExecutionTask::where('id', $validated['execution_task_id'])->where('project_id', $project->id)->get()
                    : $project->executionTasks;
                foreach ($tasks as $task) {
                    $surveyData->ensureDefaultTaskChecklists($task, (int) $validated['day_number']);
                }
            }

            $inserted = 0;
            foreach ($targets as $target) {
                $existing = TaskChecklist::where('survey_plan_id', $target['survey_plan_id'])
                    ->whereNull('execution_task_id')
                    ->where('day_number', $validated['day_number'])
                    ->pluck('item_title')
                    ->all();
                $payloads = [];
                foreach ($items as $title) {
                    if (in_array($title, $existing, true)) {
                        continue;
                    }
                    $payloads[] = [
                        'survey_plan_id' => $target['survey_plan_id'],
                        'execution_task_id' => null,
                        'day_number' => $validated['day_number'],
                        'item_title' => $title,
                        'is_completed' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($payloads)) {
                    TaskChecklist::insert($payloads);
                    $inserted += count($payloads);
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to seed default checklists. ' . $e->getMessage()
            );
        }

        $activityService->log(
            module: 'task_checklist',
            action: 'seeded_defaults',
            actor: $actor,
            reference: null,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'day_number' => $validated['day_number'],
                'scope' => $validated['target_scope'],
                'items_seeded' => $inserted,
            ],
            request: $request
        );

        return back()->with(
            'success',
            "Seeded {$inserted} default checklist item(s) for Day {$validated['day_number']}."
        );
    }

    private function authorizeProjectWrite(Project $project): void
    {
        $actor = $this->constructionActor();
        if (!$actor) {
            abort(403, 'Authenticated construction actor is required.');
        }

        $memberId = $actor->getKey();
        $assigned = ProjectTeamMember::where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->exists();

        if ($assigned) {
            return;
        }

        $isAdmin = method_exists($actor, 'isAdmin') ? $actor->isAdmin() : false;
        if (!$isAdmin) {
            abort(403, 'You require a project-team assignment or admin role to edit this project.');
        }
    }

    private function checklistBelongsToProject(TaskChecklist $checklist, Project $project): bool
    {
        $taskProjectId = null;
        if ($checklist->execution_task_id) {
            $taskProjectId = \App\Models\ExecutionTask::where('id', $checklist->execution_task_id)->value('project_id');
        }
        $planProjectId = null;
        if ($checklist->survey_plan_id) {
            $planProjectId = \App\Models\SurveyPlan::where('id', $checklist->survey_plan_id)->value('project_id');
        }

        return !(
            ($taskProjectId !== null && (int) $taskProjectId !== (int) $project->id)
            || ($planProjectId !== null && (int) $planProjectId !== (int) $project->id)
        );
    }
}
