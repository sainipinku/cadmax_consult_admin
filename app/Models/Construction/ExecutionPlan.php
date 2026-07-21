<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExecutionPlan extends Model
{
    protected $table = 'construction_execution_plans';

    protected $fillable = [
        'project_id',
        'plan_code',
        'title',
        'description',
        'planned_start_date',
        'planned_end_date',
        'planned_progress_percent',
        'actual_progress_percent',
        'created_by_type',
        'created_by_id',
        'approved_by_type',
        'approved_by_id',
        'approved_at',
        'status',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'planned_progress_percent' => 'float',
        'actual_progress_percent' => 'float',
        'approved_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ExecutionTask::class, 'execution_plan_id');
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function approvedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
