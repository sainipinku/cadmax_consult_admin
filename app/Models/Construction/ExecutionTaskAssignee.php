<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExecutionTaskAssignee extends Model
{
    protected $table = 'construction_execution_task_assignees';

    protected $fillable = [
        'project_id',
        'execution_task_id',
        'member_id',
        'assignment_role',
        'assigned_from',
        'assigned_to',
        'is_primary',
        'assigned_by_type',
        'assigned_by_id',
        'status',
    ];

    protected $casts = [
        'assigned_from' => 'date',
        'assigned_to' => 'date',
        'is_primary' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function executionTask(): BelongsTo
    {
        return $this->belongsTo(ExecutionTask::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function assignedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
