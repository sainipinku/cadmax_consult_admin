<?php

use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\TaskChecklist;
use App\Models\ConstructionDocument;
use App\Models\DailyProgressReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $execTasksTable = (new ExecutionTask())->getTable();

        $execCols = Schema::getColumnListing($execTasksTable);

        $safeSelect = array_values(array_intersect([
            'id','project_id','execution_plan_id','parent_task_id','task_code',
            'title','description','planned_start_date','planned_end_date',
            'actual_start_date','actual_end_date','priority','planned_quantity',
            'completed_quantity','unit','progress_percent','requires_daily_update',
            'requires_gps_verification','supervisor_member_id','status',
            'supervisor_approved_at','supervisor_notes','created_by_type',
            'created_by_id','client_review_status','location_name','latitude',
            'longitude','sort_order','created_at','updated_at','deleted_at','created_by'
        ], $execCols));

        if (empty($safeSelect)) {
            return;
        }

        $executionTasks = DB::table($execTasksTable)->select($safeSelect)->get();

        $oldNewTaskMap = [];

        DB::transaction(function () use ($executionTasks, $execCols, &$oldNewTaskMap) {
            foreach ($executionTasks as $et) {
                $arr = (array) $et;

                $clientReviewStatusRaw = $arr['client_review_status'] ?? null;
                $clientReviewStatus = in_array($clientReviewStatusRaw, ['pending','requested','approved','revision_requested','rejected'], true)
                    ? $clientReviewStatusRaw
                    : 'pending';

                $priority = in_array(strtolower((string) ($arr['priority'] ?? 'medium')), ['low','medium','high','critical'], true)
                    ? strtolower((string) $arr['priority'])
                    : 'medium';

                $status = $this->normalizeStatus((string) ($arr['status'] ?? 'pending'));

                $taskCode = !empty($arr['task_code']) ? $arr['task_code'] : null;

                $startDate = $arr['actual_start_date'] ?? $arr['planned_start_date'] ?? null;
                $endDate   = $arr['actual_end_date']   ?? $arr['planned_end_date']   ?? null;

                $payload = [
                    'project_id'                 => $arr['project_id'] ?? null,
                    'execution_plan_id'          => $arr['execution_plan_id'] ?? null,
                    'survey_plan_id'             => null,
                    'parent_task_id'             => null,
                    'task_code'                  => $taskCode,
                    'title'                      => $arr['title'] ?? 'Untitled Task',
                    'description'                => $arr['description'] ?? null,
                    'member_id'                  => null,
                    'start_date'                 => $startDate,
                    'end_date'                   => $endDate,
                    'status'                     => $status,
                    'completed_at'               => $status === 'completed' ? now() : null,
                    'priority'                   => $priority,
                    'progress_percent'           => max(0, min(100, (int) round((float) ($arr['progress_percent'] ?? 0)))),
                    'requires_gps_verification'  => (bool) ($arr['requires_gps_verification'] ?? false),
                    'planned_qty'                => $arr['planned_quantity'] ?? null,
                    'completed_qty'              => $arr['completed_quantity'] ?? null,
                    'qty_unit'                   => $arr['unit'] ?? null,
                    'assigned_supervisor_member_id' => $arr['supervisor_member_id'] ?? null,
                    'supervisor_approved_at'     => $arr['supervisor_approved_at'] ?? null,
                    'approved_by_type'           => null,
                    'approved_by_id'             => null,
                    'client_review_status'       => $clientReviewStatus,
                    'task_source'                => 'execution_plan_seed',
                    'created_by_type'            => $arr['created_by_type'] ?? null,
                    'created_by_id'              => $arr['created_by_id'] ?? null,
                    'latitude'                   => $arr['latitude'] ?? null,
                    'longitude'                  => $arr['longitude'] ?? null,
                    'sort_order'                 => (int) ($arr['sort_order'] ?? 0),
                    'created_at'                 => $arr['created_at'] ?? null,
                    'updated_at'                 => $arr['updated_at'] ?? null,
                    'deleted_at'                 => $arr['deleted_at'] ?? null,
                ];

                $legacyCreatedBy = $arr['created_by'] ?? null;
                if (!empty($legacyCreatedBy) && empty($payload['created_by_type'])) {
                    $payload['created_by_type'] = 'super_admin';
                    $payload['created_by_id']   = (int) $legacyCreatedBy;
                }

                $taskCodeCandidate = $taskCode ?? sprintf('ET-%s-%06d', (int) ($arr['project_id'] ?? 0), (int) $arr['id']);

                $existing = \App\Models\Task::query()
                    ->where('project_id', (int) ($arr['project_id'] ?? 0))
                    ->where(function ($q) use ($arr, $taskCodeCandidate) {
                        if (!empty($arr['task_code'])) {
                            $q->where('task_code', $arr['task_code']);
                        }
                        $q->orWhere('task_code', $taskCodeCandidate);
                    })
                    ->first();

                if ($existing) {
                    $existing->fill(array_filter($payload, static fn ($v) => $v !== null));
                    $existing->saveQuietly();
                    $newTask = $existing;
                } else {
                    $payload['task_code'] = $taskCodeCandidate;
                    $newTask = \App\Models\Task::query()->create($payload);
                }

                $oldNewTaskMap[(int) $arr['id']] = (int) $newTask->id;
            }

            $this->repairParentTaskReferences($oldNewTaskMap, $execCols);
            $this->backfillAssignments($oldNewTaskMap);
            $this->backfillChecklists($oldNewTaskMap);
            $this->backfillDocuments($oldNewTaskMap);
            $this->backfillDprs($oldNewTaskMap);
        });
    }

    public function down(): void
    {
    }

    private function normalizeStatus(string $in): string
    {
        $map = [
            'draft' => 'planned',
            'planned' => 'planned',
            'pending' => 'pending',
            'assigned' => 'pending',
            'not_started' => 'pending',
            'in_progress' => 'in_progress',
            'in-progress' => 'in_progress',
            'active' => 'in_progress',
            'progress' => 'in_progress',
            'in_review' => 'review',
            'review' => 'review',
            'submitted' => 'review',
            'completed' => 'completed',
            'approved' => 'completed',
            'done' => 'completed',
            'rejected' => 'blocked',
            'blocked' => 'blocked',
            'on_hold' => 'blocked',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
        ];
        return $map[strtolower(trim($in))] ?? 'pending';
    }

    private function repairParentTaskReferences(array $map, array $execCols): void
    {
        if (!in_array('parent_task_id', $execCols, true)) {
            return;
        }
        $rows = DB::table((new ExecutionTask())->getTable())
            ->whereNotNull('parent_task_id')
            ->select(['id','parent_task_id'])
            ->get();
        foreach ($rows as $row) {
            $newChildId = $map[(int) $row->id] ?? null;
            $newParentId = $map[(int) $row->parent_task_id] ?? null;
            if ($newChildId && $newParentId) {
                \App\Models\Task::query()
                    ->where('id', $newChildId)
                    ->update(['parent_task_id' => $newParentId]);
            }
        }
    }

    private function backfillAssignments(array $map): void
    {
        $eaTable = (new ExecutionTaskAssignee())->getTable();
        if (!Schema::hasTable($eaTable)) {
            return;
        }
        $eaCols = Schema::getColumnListing($eaTable);

        $query = DB::table($eaTable);
        if (in_array('execution_task_id', $eaCols, true)) {
            $query->whereIn('execution_task_id', array_keys($map));
        }

        foreach ($query->cursor() as $ea) {
            $arr = (array) $ea;
            $taskFk = $arr['execution_task_id'] ?? null;
            $newTaskId = $taskFk !== null ? ($map[(int) $taskFk] ?? null) : null;
            if (!$newTaskId) {
                continue;
            }

            $role = in_array($arr['assignment_role'] ?? null, ['owner','executor','reviewer','checker','verifier'], true)
                ? $arr['assignment_role']
                : 'executor';

            $status = in_array($arr['status'] ?? null, ['pending_acceptance','active','completed','revoked','rejected'], true)
                ? $arr['status']
                : 'active';

            $row = [
                'task_id'          => $newTaskId,
                'project_id'       => $arr['project_id'] ?? null,
                'assignment_role'  => $role,
                'assigned_from'    => isset($arr['start_date']) ? $this->toDateTime($arr['start_date'], '09:00:00') : null,
                'assigned_until'   => isset($arr['end_date'])   ? $this->toDateTime($arr['end_date'],   '18:00:00') : null,
                'is_primary'       => (bool) ($arr['is_primary'] ?? 0),
                'status'           => $status,
                'assigned_to'      => $arr['member_id'] ?? null,
                'assigned_by_type' => $arr['assigned_by_type'] ?? null,
                'assigned_by_uid'  => $arr['assigned_by_id'] ?? null,
                'accepted_at'      => null,
                'uuid'             => $arr['uuid'] ?? (string) Illuminate\Support\Str::uuid(),
                'created_at'       => $arr['created_at'] ?? null,
                'updated_at'       => $arr['updated_at'] ?? null,
            ];

            $exists = DB::table('task_assignments')
                ->where('task_id', $newTaskId)
                ->where('assigned_to', $row['assigned_to'])
                ->whereNull('deleted_at')
                ->first();

            if ($exists) {
                DB::table('task_assignments')
                    ->where('id', $exists->id)
                    ->update(array_filter($row, static fn ($v) => $v !== null));
            } else {
                DB::table('task_assignments')->insert($row);
            }
        }
    }

    private function backfillChecklists(array $map): void
    {
        $clTable = (new TaskChecklist())->getTable();
        if (!Schema::hasTable($clTable)) {
            return;
        }
        $clCols = Schema::getColumnListing($clTable);
        if (!in_array('execution_task_id', $clCols, true)) {
            return;
        }

        foreach (DB::table($clTable)->whereNotNull('execution_task_id')->cursor() as $cl) {
            $arr = (array) $cl;
            $newTaskId = $map[(int) $arr['execution_task_id']] ?? null;
            if (!$newTaskId) {
                continue;
            }
            $projectId = \App\Models\Task::query()->where('id', $newTaskId)->value('project_id');

            $sourceRaw = $arr['source'] ?? null;
            if (is_numeric($sourceRaw)) {
                $source = match ((int) $sourceRaw) {
                    1 => 'default_seeded',
                    2 => 'admin_custom',
                    3 => 'member_added',
                    default => 'default_seeded',
                };
            } else {
                $source = in_array($sourceRaw, ['default_seeded','admin_custom','member_added','checklist_seed'], true)
                    ? $sourceRaw
                    : 'default_seeded';
            }

            $payload = [
                'task_id'                => $newTaskId,
                'project_id'             => $projectId,
                'day_number'             => $arr['day_number'] ?? null,
                'item_title'             => $arr['item_title'] ?? '(untitled)',
                'is_completed'           => (bool) ($arr['is_completed'] ?? 0),
                'sort_order'             => (int) ($arr['sort_order'] ?? 0),
                'completed_by_member_id' => $arr['completed_by_member_id'] ?? null,
                'completed_at'           => $arr['completed_at'] ?? null,
                'source'                 => $source,
                'client_reference'       => $arr['client_reference'] ?? (string) Illuminate\Support\Str::uuid(),
                'created_by_type'        => 'super_admin',
                'created_by_id'          => null,
                'created_at'             => $arr['created_at'] ?? null,
                'updated_at'             => $arr['updated_at'] ?? null,
                'deleted_at'             => null,
            ];

            $exists = DB::table('task_checklist_items')
                ->where('task_id', $newTaskId)
                ->where('item_title', (string) $payload['item_title'])
                ->where(function ($q) use ($payload) {
                    if (!empty($payload['client_reference'])) {
                        $q->orWhere('client_reference', $payload['client_reference']);
                    }
                })
                ->first();

            if ($exists) {
                DB::table('task_checklist_items')
                    ->where('id', $exists->id)
                    ->update(array_filter($payload, static fn ($v) => $v !== null));
            } else {
                DB::table('task_checklist_items')->insert($payload);
            }
        }
    }

    private function backfillDocuments(array $map): void
    {
        $docTable = (new ConstructionDocument())->getTable();
        if (!Schema::hasTable($docTable)) {
            return;
        }
        $execTaskFqcn = ExecutionTask::class;
        $newType = \App\Models\Task::class;

        foreach (ConstructionDocument::query()
            ->where('documentable_type', $execTaskFqcn)
            ->cursor() as $doc) {
            $newTaskId = $map[(int) $doc->documentable_id] ?? null;
            if (!$newTaskId) {
                continue;
            }
            $exists = ConstructionDocument::query()
                ->where('documentable_type', $newType)
                ->where('documentable_id', $newTaskId)
                ->where('file_path', $doc->file_path)
                ->exists();
            if ($exists) {
                continue;
            }
            $clone = $doc->replicate();
            $clone->documentable_type = $newType;
            $clone->documentable_id   = $newTaskId;
            $clone->project_id        = $clone->project_id ?? (\App\Models\Task::query()->where('id', $newTaskId)->value('project_id'));
            $clone->saveQuietly();
        }
    }

    private function backfillDprs(array $map): void
    {
        $dprTable = (new DailyProgressReport())->getTable();
        if (!Schema::hasTable($dprTable)) {
            return;
        }
        $hasPrimaryFk = Schema::hasColumn($dprTable, 'primary_task_id');

        foreach (DailyProgressReport::query()->whereNotNull('execution_task_id')->cursor() as $dpr) {
            $newTaskId = $map[(int) $dpr->execution_task_id] ?? null;
            if (!$newTaskId) {
                continue;
            }
            if ($hasPrimaryFk && empty($dpr->primary_task_id)) {
                $dpr->primary_task_id = $newTaskId;
                $dpr->saveQuietly();
            }
        }
    }

    private function toDateTime(mixed $input, string $defaultTime): ?string
    {
        if ($input instanceof \DateTimeInterface) {
            return $input->format('Y-m-d H:i:s');
        }
        if (empty($input)) {
            return null;
        }
        try {
            $d = is_string($input) ? new \DateTimeImmutable($input) : new \DateTimeImmutable('@' . (int) $input);
            if (strlen((string) $input) <= 10) {
                return $d->format('Y-m-d ') . $defaultTime;
            }
            return $d->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
};
