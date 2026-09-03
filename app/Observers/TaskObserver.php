<?php

namespace App\Observers;

use App\Enums\TaskCommentKind;
use App\Models\Member;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class TaskObserver
{
    public function creating(Task $task): void
    {
        if (empty($task->uuid)) {
            $task->uuid = (string) Str::uuid();
        }
        if (empty($task->client_reference) && $task->project_id) {
            $task->client_reference = (string) Str::uuid();
        }
        if (empty($task->task_code) && $task->project_id) {
            $task->task_code = $this->generateTaskCode($task);
        }
        if (is_null($task->task_source)) {
            $task->task_source = 'admin_created';
        }
        if (empty($task->created_by_type) && !empty($task->created_by) && is_numeric($task->created_by)) {
            $task->created_by_type = 'App\\Models\\SuperAdmin';
            $task->created_by_id   = (int) $task->created_by;
        }
        $this->recomputeProgress($task);
    }

    public function saving(Task $task): void
    {
        if (empty($task->created_by_type) && !empty($task->created_by) && is_numeric($task->created_by)) {
            $task->created_by_type = 'App\\Models\\SuperAdmin';
            $task->created_by_id   = (int) $task->created_by;
        }
        if (empty($task->task_code) && $task->project_id) {
            $task->task_code = $this->generateTaskCode($task);
        }
        if (!is_null($task->status) && strtolower((string) $task->status) === 'completed') {
            if (is_null($task->completed_at)) {
                $task->completed_at = now();
            }
        }
        $this->recomputeProgress($task);
    }

    public function saved(Task $task): void
    {
        $this->denormalizeAssigneesProjectId($task);
    }

    public function deleting(Task $task): void
    {
        try {
            DB::table('task_assignments')
                ->where('task_id', $task->id)
                ->whereNull('deleted_at')
                ->update(['status' => 'revoked', 'deleted_at' => now()]);
        } catch (Throwable) {
        }
    }

    private function recomputeProgress(Task $task): void
    {
        if ($task->planned_qty > 0 && $task->completed_qty !== null) {
            $derived = (int) round(((float) $task->completed_qty / (float) $task->planned_qty) * 100);
            if ($derived >= 0 && $derived <= 100) {
                $task->progress_percent = max(0, min(100, $derived));
            }
        }
        if ($task->progress_percent < 0) {
            $task->progress_percent = 0;
        }
        if ($task->progress_percent > 100) {
            $task->progress_percent = 100;
        }
        if (!$task->exists && (int) $task->progress_percent >= 100 && empty($task->status)) {
            $task->status = 'completed';
        }
    }

    private function generateTaskCode(Task $task): string
    {
        $abbr = 'T';
        if ($task->project_id) {
            $project = DB::table('construction_projects')
                ->where('id', $task->project_id)
                ->select(['project_code', 'project_name'])
                ->first();
            if ($project) {
                $base = !empty($project->project_code) ? $project->project_code : $project->project_name;
                $words = preg_split('/[^A-Za-z0-9]+/', (string) $base, -1, PREG_SPLIT_NO_EMPTY);
                $abbr = strtoupper(implode('', array_map(static fn (string $w): string => Str::upper(Str::substr($w, 0, 1)), array_slice($words, 0, 3))));
                if ($abbr === '' || strlen($abbr) > 8) {
                    $abbr = 'PRJ';
                }
            }
        }
        try {
            return DB::transaction(function () use ($task, $abbr): string {
                for ($attempt = 0; $attempt < 5; ++$attempt) {
                    $next = (int) DB::table('tasks')
                        ->where('project_id', $task->project_id)
                        ->lockForUpdate()
                        ->count() + 1 + $attempt;
                    $candidate = sprintf('%s-T-%05d', $abbr, $next);
                    $used = DB::table('tasks')->where('task_code', $candidate)->exists();
                    if (!$used) {
                        return $candidate;
                    }
                }
                return sprintf('%s-%s', $abbr, Str::upper(Str::random(8)));
            });
        } catch (Throwable) {
            return sprintf('%s-%s', $abbr, Str::upper(Str::random(8)));
        }
    }

    private function denormalizeAssigneesProjectId(Task $task): void
    {
        if (!$task->project_id) {
            return;
        }
        try {
            DB::table('task_assignments')
                ->where('task_id', $task->id)
                ->whereNull('project_id')
                ->update(['project_id' => $task->project_id]);
            DB::table('task_comments')
                ->where('task_id', $task->id)
                ->whereNull('project_id')
                ->update(['project_id' => $task->project_id]);
            DB::table('task_checklist_items')
                ->where('task_id', $task->id)
                ->whereNull('project_id')
                ->update(['project_id' => $task->project_id]);
        } catch (Throwable) {
        }
    }
}
