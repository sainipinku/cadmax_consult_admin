<?php

namespace App\Services\Construction;

use App\Enums\TaskAssignmentRole;
use App\Enums\TaskAssignmentStatus;
use App\Enums\TaskChecklistSource;
use App\Enums\TaskCommentKind;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use Illuminate\Support\Facades\Schema;
use App\Models\Admin;
use App\Models\Construction\Project;
use App\Models\ConstructionDocument;
use App\Models\DailyProgressReport;
use App\Models\ExecutionPlan;
use App\Models\Member;
use App\Models\SurveyPlan;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Rules\ValidMediaUpload;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class TaskManagementService
{
    public function __construct(
        public readonly ConstructionActivityService $activity,
        public readonly ConstructionDocumentService $documents,
        public readonly SurveyDataService $surveyData,
    ) {
    }

    /**
     * @param  Project  $project
     * @param  array  $payload
     * @param  Model  $actor   SuperAdmin|Admin|Member
     * @param  Request|null  $request
     * @return Task
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function create(Project $project, array $payload, Model $actor, ?Request $request = null): Task
    {
        $payload = $this->validateCreatePayload($project, $payload);
        return DB::transaction(function () use ($project, $payload, $actor, $request) {
            $task = new Task();
            $task->forceFill($this->taskFillableFromPayload($project, $payload, $actor));
            $task->save();
            $this->syncSurveySeededFields($task, $payload);

            $assignments = $this->replaceAssignments($task, $payload['assignments'] ?? [], $actor);
            $checklists = $this->replaceChecklistItems($task, $payload['checklist_items'] ?? [], $actor);

            if (empty($checklists) && $this->shouldSeedDefaultChecklists($task)) {
                $this->seedDefaultChecklists($task, $actor);
            }

            if (!empty($payload['files']) && is_array($payload['files'])) {
                $this->attachDocuments($task, $payload['files'], $actor);
            }

            $this->activity->log(
                module: 'task',
                action: 'created',
                actor: $actor,
                reference: $task,
                companyId: $project->company_id ?? null,
                projectId: $project->id,
                meta: [
                    'task_code' => $task->task_code,
                    'assignments' => $assignments->count(),
                    'checklist_items' => $task->checklistItems()->count(),
                ],
                request: $request
            );

            return $task->loadMissing([
                'project', 'executionPlan', 'surveyPlan', 'parent',
                'activeAssignments.assignee', 'checklistItems.completedBy',
                'latestTransition', 'documents', 'supervisor',
            ]);
        });
    }

    /**
     * @throws Throwable
     */
    public function update(Task $task, array $patch, Model $actor, ?Request $request = null): Task
    {
        $project = $task->project ?? Project::query()->findOrFail($task->project_id);
        $allowed = $this->allowedMasterFields();
        $patch = array_intersect_key($patch, array_flip($allowed));
        return DB::transaction(function () use ($task, $project, $patch, $actor, $request) {
            $before = $task->replicate();
            foreach ($patch as $key => $value) {
                $task->{$key} = $value;
            }
            $task->save();
            $this->syncSurveySeededFields($task, $patch);

            $diff = $this->diffForLog($before, $task, $allowed);
            if (!empty($diff)) {
                $this->activity->log(
                    module: 'task',
                    action: 'updated',
                    actor: $actor,
                    reference: $task,
                    companyId: $project->company_id ?? null,
                    projectId: $task->project_id,
                    meta: ['changes' => $diff],
                    request: $request,
                );
                if (isset($diff['status'])) {
                    $note = TaskComment::buildForTransition(
                        task: $task,
                        actor: $actor,
                        from: $diff['status']['before'],
                        to: $diff['status']['after'],
                    );
                    $note->saveQuietly();
                }
            }
            return $task->loadMissing($this->defaultIncludes());
        });
    }

    /**
     * Accepts either short form  [ 1, 2, 3 ] (member IDs) or detail rows:
     *   [ ['member_id'=>1,'assignment_role'=>'executor','is_primary'=>true,
     *      'assigned_from'=>'...','assigned_until'=>'...'] ]
     *
     * @return EloquentCollection<int, TaskAssignment>
     *
     * @throws Throwable
     */
    public function replaceAssignments(Task $task, array $rows, Model $actor): EloquentCollection
    {
        if (!empty($rows) && is_numeric(array_key_first($rows)) && is_numeric(reset($rows))) {
            $rows = array_values(array_map(static fn ($mid) => ['member_id' => (int) $mid], $rows));
        }

        $rows = collect($rows)
            ->filter(static fn ($row) => is_array($row) && !empty($row['member_id']))
            ->values();

        return DB::transaction(function () use ($task, $rows, $actor) {
            $existing = TaskAssignment::query()
                ->where('task_id', $task->id)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy(static fn (TaskAssignment $a) => (int) $a->assigned_to);

            $incoming = $rows->keyBy(static fn (array $r) => (int) $r['member_id']);

            $keep = $existing->intersectByKeys($incoming);
            $remove = $existing->diffKeys($incoming);
            $add = $incoming->diffKeys($existing);

            $keep->each(function (TaskAssignment $assign) use ($incoming, $task, $actor) {
                $row = $incoming[(int) $assign->assigned_to];
                $payload = $this->assignmentRowPayload($task, $row, $actor, patch: true);
                foreach ($payload as $k => $v) {
                    $assign->{$k} = $v;
                }
                if ($assign->isDirty()) {
                    $assign->save();
                }
            });

            $remove->each(function (TaskAssignment $assign) use ($actor, $task) {
                $assign->status = (TaskAssignmentStatus::REVOKED->value ?? 'revoked');
                $assign->deleted_at = now();
                $assign->saveQuietly();
                $this->activity->log(
                    module: 'task_assignment',
                    action: 'revoked',
                    actor: $actor,
                    reference: $task,
                    projectId: $task->project_id,
                    meta: ['assigned_to' => $assign->assigned_to],
                );
            });

            $add->each(function (array $row) use ($task, $actor) {
                $payload = $this->assignmentRowPayload($task, $row, $actor, patch: false);
                /** @var TaskAssignment $assign */
                $assign = TaskAssignment::query()->create($payload);
                $this->activity->log(
                    module: 'task_assignment',
                    action: 'assigned',
                    actor: $actor,
                    reference: $task,
                    projectId: $task->project_id,
                    meta: [
                        'assignment_id' => $assign->id,
                        'assigned_to' => $assign->assigned_to,
                        'role' => $assign->assignment_role,
                    ],
                );
            });

            return TaskAssignment::query()
                ->where('task_id', $task->id)
                ->whereNull('deleted_at')
                ->where('status', TaskAssignmentStatus::ACTIVE->value ?? 'active')
                ->with(['assignee:id,name,email,profile_photo_path,mobile'])
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get();
        });
    }

    /**
     * @return TaskComment Transition record (kind=status_note) with documents loaded.
     *
     * @throws ValidationException|Throwable
     */
    public function transitionStatus(
        Task $task,
        string $toStatus,
        Member $actor,
        ?Request $request = null,
        array $options = []
    ): TaskComment {
        $note = $options['note'] ?? null;
        $files = $options['files'] ?? [];
        $latitude = $options['latitude'] ?? null;
        $longitude = $options['longitude'] ?? null;
        $progressOverride = $options['progress_percent'] ?? null;
        $completedQtyOverride = $options['completed_qty'] ?? null;
        $meta = $options['meta'] ?? null;

        $from = (string) ($task->status ?? 'pending');
        $fromEnum = $this->toStatusEnum($from);
        $toEnum = $this->toStatusEnum($toStatus);

        if (!$fromEnum->canTransitionTo($toEnum)) {
            $validNext = collect(TaskStatus::cases())
                ->filter(static fn (TaskStatus $c) => $fromEnum->canTransitionTo($c))
                ->map(static fn (TaskStatus $c) => $c->value)
                ->values()->all();
            throw ValidationException::withMessages([
                'to_status' => [sprintf(
                    'Invalid status transition from "%s" to "%s". Allowed: %s.',
                    $fromEnum->value,
                    $toEnum->value,
                    implode(', ', $validNext)
                )],
            ])->errorBag('default')->status(422);
        }

        if ($task->requires_gps_verification && ($latitude === null || $longitude === null)) {
            throw ValidationException::withMessages([
                'latitude' => ['GPS coordinates are required for this task.'],
                'longitude' => ['GPS coordinates are required for this task.'],
            ])->errorBag('default')->status(403);
        }

        $latRule = 'nullable|numeric|between:-90,90';
        $lngRule = 'nullable|numeric|between:-180,180';
        $v = Validator::make(get_defined_vars(), [
            'latitude' => $latRule,
            'longitude' => $lngRule,
            'progressOverride' => 'nullable|integer|between:0,100',
            'note' => 'nullable|string|max:5000',
        ]);
        if ($v->fails()) {
            throw new ValidationException($v);
        }

        return DB::transaction(function () use (
            $task, $fromEnum, $toEnum, $actor, $request,
            $note, $files, $latitude, $longitude, $progressOverride, $completedQtyOverride, $meta
        ) {
            $statusBefore = $fromEnum->value;
            $progressBefore = (int) ($task->progress_percent ?? 0);
            $qtyBefore = $task->completed_qty;

            $task->status = $toEnum->value;
            if ($progressOverride !== null) {
                $task->progress_percent = max(0, min(100, (int) $progressOverride));
            }
            if ($completedQtyOverride !== null) {
                $task->completed_qty = $completedQtyOverride;
            }
            if ($toEnum === TaskStatus::COMPLETED) {
                $task->completed_at = now();
                if ($progressOverride === null) {
                    $task->progress_percent = 100;
                }
            } elseif ($toEnum === TaskStatus::IN_PROGRESS && $task->completed_at !== null) {
                $task->completed_at = null;
            }
            $task->save();

            if (is_array($meta)) {
                $meta = array_merge($meta, [
                    'progress_before' => $progressBefore,
                    'progress_after' => (int) $task->progress_percent,
                    'completed_qty_before' => $qtyBefore,
                    'completed_qty_after' => $task->completed_qty,
                ]);
            }

            $transition = TaskComment::buildForTransition(
                task: $task,
                actor: $actor,
                from: $statusBefore,
                to: $toEnum->value,
                note: $note,
                latitude: $latitude === null ? null : (float) $latitude,
                longitude: $longitude === null ? null : (float) $longitude,
                meta: $meta,
            );
            $transition->save();

            if (!empty($files) && is_array($files)) {
                $this->attachDocuments($transition, $files, $actor);
            }

            $this->activity->log(
                module: 'task',
                action: 'status_changed',
                actor: $actor,
                reference: $task,
                projectId: $task->project_id,
                meta: [
                    'from' => $statusBefore,
                    'to' => $toEnum->value,
                    'files' => is_countable($files) ? count($files) : 0,
                    'transition_id' => $transition->id,
                ],
                request: $request,
            );

            return $transition->loadMissing(['documents', 'commenter']);
        });
    }

    /**
     * @return LengthAwarePaginator<int, Task>|EloquentCollection<int, Task>
     */
    public function index(Project $project, array $filters = [], ?int $perPage = 25)
    {
        $builder = $this->buildIndexQuery($project, $filters);
        if ($perPage === null) {
            return $builder->with($this->defaultIncludes())->get();
        }
        return $builder
            ->with($this->defaultIncludes())
            ->paginate($perPage)
            ->withQueryString();
    }

    public function buildIndexQuery(Project $project, array $filters): Builder
    {
        $q = Task::query()->forProject($project);

        if (!empty($filters['status'])) {
            $q->whereIn('status', (array) $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $q->whereIn('priority', (array) $filters['priority']);
        }
        if (!empty($filters['assignee_id'])) {
            $ids = array_map('intval', (array) $filters['assignee_id']);
            $q->whereHas('activeAssignments', static function (Builder $sq) use ($ids) {
                $sq->whereIn('assigned_to', $ids);
            });
        }
        if (!empty($filters['search'])) {
            $q->search((string) $filters['search']);
        }
        if (!empty($filters['task_source'])) {
            $q->whereIn('task_source', (array) $filters['task_source']);
        }
        if (!empty($filters['execution_plan_id'])) {
            $q->where('execution_plan_id', (int) $filters['execution_plan_id']);
        }
        if (!empty($filters['survey_plan_id'])) {
            $q->where('survey_plan_id', (int) $filters['survey_plan_id']);
        }
        if (!empty($filters['parent_task_id'])) {
            $q->where('parent_task_id', (int) $filters['parent_task_id']);
        } elseif (!empty($filters['only_roots'])) {
            $q->whereNull('parent_task_id');
        }

        $sortBy = in_array($filters['sort_by'] ?? 'sort_order', ['sort_order','priority','created_at','updated_at','progress_percent','end_date','start_date','task_code'], true)
            ? (string) $filters['sort_by']
            : 'sort_order';
        $dir = strtolower((string) ($filters['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sortBy === 'priority') {
            $q->orderByRaw(
                'CASE priority WHEN ? THEN 3 WHEN ? THEN 2 WHEN ? THEN 1 WHEN ? THEN 0 END ' . $dir,
                [TaskPriority::CRITICAL->value ?? 'critical', TaskPriority::HIGH->value ?? 'high', TaskPriority::MEDIUM->value ?? 'medium', TaskPriority::LOW->value ?? 'low']
            );
            $q->orderBy('sort_order', 'asc')->orderBy('id', 'desc');
        } else {
            $q->orderBy($sortBy, $dir);
            if ($sortBy !== 'sort_order') {
                $q->orderBy('sort_order', 'asc');
            }
            $q->orderBy('id', 'desc');
        }

        return $q;
    }

    public function toggleChecklistItem(
        Task $task,
        int|string $checklistIdOrClientRef,
        bool $completed,
        Member $actor,
        ?Request $request = null,
    ): TaskChecklistItem {
        $item = TaskChecklistItem::query()
            ->where('task_id', $task->id)
            ->where(static function (Builder $q) use ($checklistIdOrClientRef) {
                if (is_numeric($checklistIdOrClientRef)) {
                    $q->where('id', (int) $checklistIdOrClientRef);
                }
                if (is_string($checklistIdOrClientRef) && Str::isUuid($checklistIdOrClientRef)) {
                    $q->orWhere('client_reference', $checklistIdOrClientRef);
                }
            })
            ->firstOrFail();

        return DB::transaction(function () use ($item, $completed, $actor, $task, $request) {
            $before = (bool) $item->is_completed;
            $item->toggleComplete($completed, $actor);
            $item->updated_by_type = morph_type($actor);
            $item->updated_by_id = $actor->getKey();
            $item->save();
            if ($before !== (bool) $item->is_completed) {
                $this->activity->log(
                    module: 'task_checklist',
                    action: $completed ? 'completed' : 'reopened',
                    actor: $actor,
                    reference: $task,
                    projectId: $task->project_id,
                    meta: ['checklist_item_id' => $item->id, 'title' => $item->item_title],
                    request: $request,
                );
            }
            return $item;
        });
    }

    /**
     * @return EloquentCollection<int, TaskChecklistItem>
     *
     * @throws Throwable
     */
    public function replaceChecklistItems(Task $task, array $items, Model $actor): EloquentCollection
    {
        $items = collect($items)->values();

        return DB::transaction(function () use ($task, $items, $actor) {
            $existingById = TaskChecklistItem::query()
                ->where('task_id', $task->id)
                ->get()
                ->keyBy(static fn (TaskChecklistItem $c) => (int) $c->id);

            $byExistingId = $items
                ->filter(static fn ($row) => !empty($row['id']) && $existingById->has((int) $row['id']))
                ->keyBy(static fn ($row) => (int) $row['id']);

            $create = $items->filter(static fn ($row) => empty($row['id'])
                || !$existingById->has((int) $row['id']));

            $keep = $existingById->intersectByKeys($byExistingId);
            $remove = $existingById->diffKeys($byExistingId);

            $sortCursor = 0;
            $byExistingId->each(static function (array $row, int $id) use ($existingById, &$sortCursor) {
                /** @var TaskChecklistItem $item */
                $item = $existingById[$id];
                $item->item_title = $row['item_title'] ?? $item->item_title;
                $item->day_number = $row['day_number'] ?? $item->day_number;
                $item->sort_order = $row['sort_order'] ?? ($sortCursor++);
                $item->is_completed = isset($row['is_completed']) ? (bool) $row['is_completed'] : $item->is_completed;
                if (!empty($row['client_reference']) && empty($item->client_reference)) {
                    $item->client_reference = $row['client_reference'];
                }
                if ($item->isDirty()) {
                    $item->save();
                }
            });

            $keep->each(function (TaskChecklistItem $item) use ($items, $existingById) {
                // No-op: already updated above. Keeps $keep var referenced for clarity.
            });

            $remove->each(function (TaskChecklistItem $item) use ($actor, $task) {
                if (Carbon::now()->subDays(90)->greaterThan($item->created_at)) {
                    $item->forceDelete();
                } else {
                    $item->sort_order = -1;
                    $item->saveQuietly();
                    $item->delete();
                }
                $this->activity->log(
                    module: 'task_checklist',
                    action: 'deleted',
                    actor: $actor,
                    reference: $task,
                    projectId: $task->project_id,
                    meta: ['checklist_item_id' => $item->id, 'title' => $item->item_title],
                );
            });

            $create->each(function (array $row) use ($task, $actor, &$sortCursor) {
                $payload = [
                    'task_id' => $task->id,
                    'project_id' => $task->project_id,
                    'day_number' => $row['day_number'] ?? null,
                    'item_title' => $row['item_title'],
                    'sort_order' => $row['sort_order'] ?? ($sortCursor++),
                    'is_completed' => !empty($row['is_completed']),
                    'source' => in_array(($row['source'] ?? null), ['default_seeded','admin_custom','member_added','checklist_seed'], true)
                        ? $row['source']
                        : (TaskChecklistSource::ADMIN_CUSTOM->value ?? 'admin_custom'),
                    'client_reference' => $row['client_reference'] ?? (string) Str::uuid(),
                    'created_by_type' => morph_type($actor),
                    'created_by_id' => $actor->getKey(),
                ];
                if (!empty($payload['is_completed']) && $payload['is_completed']) {
                    $payload['completed_by_member_id'] = $actor instanceof Member ? $actor->id : null;
                    $payload['completed_at'] = now();
                }
                TaskChecklistItem::query()->create($payload);
            });

            return TaskChecklistItem::query()
                ->where('task_id', $task->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }

    /**
     * @return EloquentCollection<int, ConstructionDocument>
     *
     * @throws Throwable
     */
    public function attachDocuments(Model $target, array $files, Model $actor, ?string $disk = null): EloquentCollection
    {
        $results = new EloquentCollection();
        $projectId = match (true) {
            $target instanceof Task => $target->project_id,
            $target instanceof TaskComment => $target->project_id,
            method_exists($target, 'project_id') => $target->project_id,
            default => null,
        };
        if (is_numeric($projectId)) {
            $projectId = (int) $projectId;
        } else {
            $projectId = null;
        }

        foreach ($files as $idx => $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            $validator = Validator::make(['file' => $file], [
                'file' => ['file', new ValidMediaUpload()],
            ]);
            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    "files.$idx" => $validator->errors()->all(),
                ]);
            }

            $folder = $this->resolveFolderFor($target);
            $companyId = $this->resolveCompanyId($projectId);

            $doc = $this->documents->storeDocument(
                documentable: $target,
                actor: $actor,
                folder: $folder,
                file: $file,
                companyId: $companyId,
                projectId: $projectId,
                disk: $disk,
            );
            $results->push($doc);
        }

        return $results;
    }

    public function deleteTask(Task $task, Model $actor, ?Request $request = null): void
    {
        DB::transaction(function () use ($task, $actor, $request) {
            $task->checklistItems()->eachById(function (TaskChecklistItem $item) {
                if (Carbon::now()->subDays(90)->greaterThan($item->created_at)) {
                    $item->forceDelete();
                } else {
                    $item->delete();
                }
            });

            TaskAssignment::query()->where('task_id', $task->id)->update([
                'status' => (TaskAssignmentStatus::REVOKED->value ?? 'revoked'),
                'deleted_at' => now(),
            ]);

            if (Schema::hasColumn((new DailyProgressReport())->getTable(), 'primary_task_id')) {
                DailyProgressReport::query()
                    ->where('primary_task_id', $task->id)
                    ->update(['primary_task_id' => null]);
            }
            DailyProgressReport::query()
                ->where('execution_task_id', $task->id)
                ->update(['execution_task_id' => null]);

            $meta = [
                'task_code' => $task->task_code,
                'task_title' => $task->title,
            ];
            $task->deleted_by_type = morph_type($actor);
            $task->deleted_by_id = $actor->getKey();
            $task->saveQuietly();
            $task->delete();

            $this->activity->log(
                module: 'task',
                action: 'deleted',
                actor: $actor,
                reference: $task,
                projectId: $task->project_id,
                meta: $meta,
                request: $request,
            );
        });
    }

    public function deltaSince(Project $project, CarbonInterface $since): array
    {
        $tasks = Task::query()
            ->forProject($project)
            ->where(fn (Builder $q) => $q->where('updated_at', '>=', $since)->orWhere('deleted_at', '>=', $since))
            ->withTrashed()
            ->select([
                'id','uuid','task_code','project_id','title','status','priority',
                'progress_percent','category','sort_order','parent_task_id','survey_plan_id',
                'execution_plan_id','assigned_supervisor_member_id','start_date','end_date',
                'completed_at','client_review_status','task_source','latitude','longitude',
                'created_at','updated_at','deleted_at',
            ])
            ->get();

        $ids = $tasks->modelKeys();
        $assigneesDirty = empty($ids) ? new EloquentCollection() : TaskAssignment::query()
            ->whereIn('task_id', $ids)
            ->where(fn (Builder $q) => $q->where('updated_at', '>=', $since)->orWhere('deleted_at', '>=', $since))
            ->withTrashed()
            ->select(['id','task_id','assigned_to','assignment_role','is_primary','status','deleted_at','updated_at'])
            ->get();

        $checklistsDirty = empty($ids) ? new EloquentCollection() : TaskChecklistItem::query()
            ->whereIn('task_id', $ids)
            ->where(fn (Builder $q) => $q->where('updated_at', '>=', $since)->orWhere('deleted_at', '>=', $since))
            ->withTrashed()
            ->select(['id','task_id','item_title','is_completed','sort_order','day_number','deleted_at','updated_at'])
            ->get();

        $transitionsDirty = empty($ids) ? new EloquentCollection() : TaskComment::query()
            ->whereIn('task_id', $ids)
            ->where('kind', TaskCommentKind::STATUS_NOTE->value ?? 'status_note')
            ->where('created_at', '>=', $since)
            ->select(['id','task_id','from_status','to_status','comment','latitude','longitude','created_at'])
            ->latest('id')
            ->get();

        return [
            'since' => $since->toIso8601String(),
            'generated_at' => Carbon::now()->toIso8601String(),
            'project_id' => $project->id,
            'tasks' => $tasks,
            'assignments' => $assigneesDirty,
            'checklist_items' => $checklistsDirty,
            'latest_transitions' => $transitionsDirty,
        ];
    }

    /* ----------------------------- HELPERS ----------------------------- */

    /**
     * @throws ValidationException
     */
    public function validateCreatePayload(Project $project, array $payload): array
    {
        $taskStatuses = collect(TaskStatus::cases())->map(static fn (TaskStatus $s) => $s->value)->all();
        $priorityValues = ['low','medium','high','critical'];
        $sources = ['admin_created','member_manual','execution_plan_seed','survey_checklist_seed'];

        $v = Validator::make($payload, [
            'title' => 'required|string|max:500',
            'description' => 'nullable|string|max:10000',
            'task_code' => 'nullable|string|max:60|unique:tasks,task_code',
            'category' => 'nullable|string|max:120',
            'status' => ['nullable','string', 'in:' . implode(',', $taskStatuses)],
            'priority' => ['nullable','string','in:' . implode(',', $priorityValues)],
            'task_source' => ['nullable','string','in:' . implode(',', $sources)],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'execution_plan_id' => [
                'nullable',
                'integer',
                RuleExists(ExecutionPlan::class, 'id', fn (Builder $q) => $q->where('project_id', $project->id)),
            ],
            'survey_plan_id' => [
                'nullable',
                'integer',
                RuleExists(SurveyPlan::class, 'id', fn (Builder $q) => $q->where('project_id', $project->id)),
            ],
            'parent_task_id' => [
                'nullable',
                'integer',
                RuleExists(Task::class, 'id', fn (Builder $q) => $q->where('project_id', $project->id)),
            ],
            'progress_percent' => 'nullable|integer|between:0,100',
            'planned_qty' => 'nullable|numeric|min:0',
            'completed_qty' => 'nullable|numeric|min:0',
            'qty_unit' => 'nullable|string|max:30',
            'requires_gps_verification' => 'nullable|boolean',
            'assigned_supervisor_member_id' => 'nullable|integer|exists:members,id',
            'client_reference' => 'nullable|uuid|unique:tasks,client_reference',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'sort_order' => 'nullable|integer|min:0',
            'assignments' => 'nullable|array',
            'assignments.*.member_id' => 'required|integer|exists:members,id',
            'assignments.*.assignment_role' => 'nullable|string|in:owner,executor,reviewer,checker,verifier',
            'assignments.*.is_primary' => 'nullable|boolean',
            'assignments.*.assigned_from' => 'nullable|date',
            'assignments.*.assigned_until' => 'nullable|date|after_or_equal:assignments.*.assigned_from',
            'checklist_items' => 'nullable|array',
            'checklist_items.*.item_title' => 'required|string|max:500',
            'checklist_items.*.day_number' => 'nullable|integer|min:0|max:255',
            'checklist_items.*.is_completed' => 'nullable|boolean',
            'checklist_items.*.sort_order' => 'nullable|integer|min:0',
            'checklist_items.*.client_reference' => 'nullable|uuid',
            'files' => 'nullable|array',
            'files.*' => ['file', new ValidMediaUpload()],
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        return $v->validated();
    }

    private function taskFillableFromPayload(Project $project, array $payload, Model $actor): array
    {
        $base = [
            'project_id' => $project->id,
            'execution_plan_id' => $payload['execution_plan_id'] ?? null,
            'survey_plan_id' => $payload['survey_plan_id'] ?? null,
            'parent_task_id' => $payload['parent_task_id'] ?? null,
            'task_code' => $payload['task_code'] ?? null,
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'category' => $payload['category'] ?? null,
            'status' => $payload['status'] ?? (TaskStatus::PENDING->value ?? 'pending'),
            'priority' => $payload['priority'] ?? 'medium',
            'task_source' => $payload['task_source'] ?? (TaskSource::ADMIN_CREATED->value ?? 'admin_created'),
            'start_date' => $payload['start_date'] ?? null,
            'end_date' => $payload['end_date'] ?? null,
            'progress_percent' => $payload['progress_percent'] ?? 0,
            'planned_qty' => $payload['planned_qty'] ?? null,
            'completed_qty' => $payload['completed_qty'] ?? null,
            'qty_unit' => $payload['qty_unit'] ?? null,
            'requires_gps_verification' => !empty($payload['requires_gps_verification']),
            'assigned_supervisor_member_id' => $payload['assigned_supervisor_member_id'] ?? null,
            'created_by_type' => morph_type($actor),
            'created_by_id' => $actor->getKey(),
            'client_reference' => $payload['client_reference'] ?? (string) Str::uuid(),
            'latitude' => $payload['latitude'] ?? null,
            'longitude' => $payload['longitude'] ?? null,
            'sort_order' => $payload['sort_order'] ?? null,
        ];
        if ($actor instanceof SuperAdmin && !isset($payload['created_by'])) {
            $base['created_by'] = $actor->id;
        }
        return $base;
    }

    private function assignmentRowPayload(Task $task, array $row, Model $actor, bool $patch): array
    {
        $start = $row['assigned_from'] ?? $row['start_date'] ?? $task->start_date ?? null;
        $end = $row['assigned_until'] ?? $row['end_date'] ?? $task->end_date ?? null;

        $payload = [
            'task_id' => $task->id,
            'project_id' => $task->project_id,
            'assigned_to' => (int) $row['member_id'],
            'assignment_role' => in_array($row['assignment_role'] ?? null, ['owner','executor','reviewer','checker','verifier'], true)
                ? $row['assignment_role']
                : (TaskAssignmentRole::EXECUTOR->value ?? 'executor'),
            'is_primary' => !empty($row['is_primary']),
            'assigned_from' => $start ? $this->toCarbon($start, '09:00:00')?->format('Y-m-d H:i:s') : null,
            'assigned_until' => $end ? $this->toCarbon($end, '18:00:00')?->format('Y-m-d H:i:s') : null,
            'assigned_by_type' => morph_type($actor),
            'assigned_by_uid' => $actor->getKey(),
            'uuid' => (string) Str::uuid(),
        ];
        if (!$patch) {
            $payload['status'] = (TaskAssignmentStatus::PENDING_ACCEPTANCE->value ?? 'pending_acceptance');
        }
        return $payload;
    }

    private function shouldSeedDefaultChecklists(Task $task): bool
    {
        if ($task->survey_plan_id) {
            return true;
        }
        if (($task->task_source?->value ?? (string) $task->task_source) === (TaskSource::SURVEY_CHECKLIST_SEED->value ?? 'survey_checklist_seed')) {
            return true;
        }
        try {
            $defaults = $this->surveyData->getDefaultChecklistItems();
            return is_array($defaults) && !empty($defaults);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @throws Throwable
     */
    private function seedDefaultChecklists(Task $task, Model $actor): void
    {
        try {
            $items = $this->surveyData->getDefaultChecklistItems();
        } catch (Throwable) {
            return;
        }
        if (!is_array($items) || empty($items)) {
            return;
        }
        $rows = [];
        $i = 0;
        foreach ($items as $item) {
            if (is_string($item)) {
                $title = $item;
                $day = null;
            } else {
                $title = is_array($item) ? ($item['title'] ?? $item['item_title'] ?? null) : null;
                $day = is_array($item) ? ($item['day_number'] ?? null) : null;
            }
            if (empty($title)) {
                continue;
            }
            $rows[] = [
                'item_title' => $title,
                'day_number' => $day,
                'sort_order' => $i++,
                'source' => TaskChecklistSource::DEFAULT_SEEDED->value ?? 'default_seeded',
                'client_reference' => (string) Str::uuid(),
            ];
        }
        if ($rows) {
            $this->replaceChecklistItems($task, $rows, $actor);
        }
    }

    private function syncSurveySeededFields(Task $task, array $payload): void
    {
        if (empty($task->survey_plan_id)) {
            return;
        }
        try {
            $plan = SurveyPlan::query()->with(['planMembers'])->find($task->survey_plan_id);
        } catch (Throwable) {
            return;
        }
        if (!$plan) {
            return;
        }
        if (empty($task->assigned_supervisor_member_id) && !empty($plan->supervisor_member_id)) {
            $task->assigned_supervisor_member_id = $plan->supervisor_member_id;
            $task->saveQuietly();
        }
        try {
            $planMembers = $plan->planMembers->pluck('member_id')->unique()->filter()->values()->all();
            if (!empty($planMembers)) {
                $existing = TaskAssignment::query()
                    ->where('task_id', $task->id)
                    ->whereNull('deleted_at')
                    ->pluck('assigned_to')
                    ->all();
                $missing = array_diff($planMembers, $existing);
                if ($missing) {
                    $rows = array_values(array_map(static fn (int $mid) => ['member_id' => $mid, 'assignment_role' => 'executor'], $missing));
                    $this->replaceAssignments($task, array_merge(iterator_to_array($this->currentActiveAssigneeRows($task)), $rows), $this->resolveSystemActor());
                }
            }
        } catch (Throwable) {
        }
    }

    /**
     * @return iterable<array{member_id:int, assignment_role?:string, is_primary?:bool}>
     */
    private function currentActiveAssigneeRows(Task $task): iterable
    {
        return TaskAssignment::query()
            ->where('task_id', $task->id)
            ->whereNull('deleted_at')
            ->where('status', (TaskAssignmentStatus::ACTIVE->value ?? 'active'))
            ->get(['assigned_to','assignment_role','is_primary'])
            ->map(static fn (TaskAssignment $a) => [
                'member_id' => (int) $a->assigned_to,
                'assignment_role' => (string) ($a->assignment_role?->value ?? $a->assignment_role),
                'is_primary' => (bool) $a->is_primary,
            ]);
    }

    private function resolveSystemActor(): Model
    {
        $sa = SuperAdmin::query()->first();
        return $sa ?? new class extends Model { protected $table = 'super_admins'; public function getKey() { return 1; } public function getMorphClass() { return 'super_admin'; } };
    }

    private function resolveFolderFor(Model $target): string
    {
        $pid = $target instanceof Task || $target instanceof TaskComment
            ? ($target->project_id ?? 'orphan')
            : ($target->project_id ?? 'orphan');
        $sub = match (true) {
            $target instanceof TaskComment => sprintf('task-transitions/%s', $target->id),
            $target instanceof Task => sprintf('tasks/%s', $target->uuid ?? $target->id),
            default => 'uploads',
        };
        return trim("construction/projects/{$pid}/{$sub}", '/');
    }

    private function resolveCompanyId(mixed $projectId): ?int
    {
        if (!$projectId) {
            return null;
        }
        try {
            $p = DB::table('construction_projects')->where('id', (int) $projectId)->select('company_id')->first();
            return $p?->company_id ? (int) $p->company_id : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    public function defaultIncludes(): array
    {
        return [
            'project', 'executionPlan', 'surveyPlan', 'parent',
            'activeAssignments.assignee', 'checklistItems.completedBy',
            'latestTransition', 'latestTransition.documents',
            'documents', 'supervisor', 'children',
        ];
    }

    private function allowedMasterFields(): array
    {
        return [
            'title','description','task_code','category','priority','start_date','end_date',
            'execution_plan_id','survey_plan_id','parent_task_id','progress_percent',
            'planned_qty','completed_qty','qty_unit','requires_gps_verification',
            'assigned_supervisor_member_id','client_review_status','status',
            'latitude','longitude','sort_order','client_reference',
        ];
    }

    private function diffForLog(Task $before, Task $after, array $allowedKeys): array
    {
        $out = [];
        foreach ($allowedKeys as $k) {
            $b = $before->{$k};
            $a = $after->{$k};
            if ($b instanceof CarbonInterface && $a instanceof CarbonInterface) {
                if ($b->eq($a)) {
                    continue;
                }
            } elseif ($b === $a) {
                continue;
            } elseif (is_object($b) && is_object($a) && (string)$b === (string)$a) {
                continue;
            }
            $out[$k] = [
                'before' => is_scalar($b) || $b === null ? $b : (string) $b,
                'after' => is_scalar($a) || $a === null ? $a : (string) $a,
            ];
        }
        return $out;
    }

    private function toStatusEnum(string $value): TaskStatus
    {
        try {
            return TaskStatus::from(strtolower(trim($value)));
        } catch (Throwable) {
            $map = [
                'draft' => TaskStatus::PLANNED,
                'not_started' => TaskStatus::PENDING,
                'active' => TaskStatus::IN_PROGRESS,
                'in-progress' => TaskStatus::IN_PROGRESS,
                'progress' => TaskStatus::IN_PROGRESS,
                'submitted' => TaskStatus::REVIEW,
                'in_review' => TaskStatus::REVIEW,
                'approved' => TaskStatus::COMPLETED,
                'done' => TaskStatus::COMPLETED,
                'rejected' => TaskStatus::BLOCKED,
                'on_hold' => TaskStatus::BLOCKED,
                'canceled' => TaskStatus::CANCELLED,
            ];
            return $map[strtolower(trim($value))] ?? TaskStatus::PENDING;
        }
    }

    private function toCarbon(mixed $value, string $defaultTime): ?Carbon
    {
        try {
            if ($value instanceof CarbonInterface) {
                return Carbon::instance($value);
            }
            if ($value === null || $value === '') {
                return null;
            }
            $d = Carbon::parse((string) $value);
            if (strlen((string) $value) <= 10) {
                [$h, $m, $s] = explode(':', $defaultTime . ':00:00');
                return $d->setTime((int)$h, (int)$m, (int)$s);
            }
            return $d;
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('App\Services\Construction\RuleExists')) {
    function RuleExists(string $model, string $column, ?callable $callback = null): \Illuminate\Validation\Rules\Exists
    {
        /** @var Model $instance */
        $instance = new $model();
        $rule = \Illuminate\Validation\Rule::exists($instance->getTable(), $column);
        if ($callback !== null) {
            $rule = $rule->where($callback);
        }
        return $rule;
    }
}

if (!function_exists('App\Services\Construction\morph_type')) {
    function morph_type(Model $model): string
    {
        $class = $model::class;
        try {
            $map = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        } catch (Throwable) {
            $map = [];
        }
        $key = array_search($class, $map, true);
        return is_string($key) && $key !== '' ? $key : $class;
    }
}
