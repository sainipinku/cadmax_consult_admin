<?php

namespace App\Models;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExecutionTask extends Model
{
    protected $table = 'construction_execution_tasks';

    protected $fillable = [
        'project_id',
        'execution_plan_id',
        'parent_task_id',
        'task_code',
        'title',
        'description',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'priority',
        'planned_quantity',
        'completed_quantity',
        'unit',
        'progress_percent',
        'requires_daily_update',
        'requires_gps_verification',
        'supervisor_member_id',
        'status',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'planned_quantity' => 'float',
        'completed_quantity' => 'float',
        'progress_percent' => 'float',
        'requires_daily_update' => 'boolean',
        'requires_gps_verification' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function executionPlan(): BelongsTo
    {
        return $this->belongsTo(ExecutionPlan::class);
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function childTasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'supervisor_member_id');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(ExecutionTaskAssignee::class, 'execution_task_id');
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(DailyProgressReport::class, 'execution_task_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'execution_task_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class, 'execution_task_id');
    }

    public function surveyVisits(): HasMany
    {
        return $this->hasMany(SurveyVisit::class, 'task_id');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        // 1. Direct match on ExecutionTask
        try {
            $et = $this->where($field, $value)->first();
            if ($et) {
                return $et;
            }
        } catch (\Throwable) {
        }

        // 2. Fallback: If $value is numeric, check if it's a unified Task ID
        if (is_numeric($value)) {
            try {
                $unified = \App\Models\Task::find((int) $value);
                if ($unified) {
                    $code = $unified->task_code ?: ('TSK-' . str_pad((string) $unified->id, 5, '0', STR_PAD_LEFT));
                    $et = $this->where('project_id', $unified->project_id)
                        ->where(function ($q) use ($code, $unified) {
                            $q->where('task_code', $code)->orWhere('title', $unified->title);
                        })->first();
                    if ($et) {
                        return $et;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
