<?php

namespace App\Models;

use App\Enums\TaskAssignmentRole;
use App\Enums\TaskAssignmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskAssignment extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasUuids;

    protected $fillable = [
        'uuid',
        'task_id',
        'project_id',
        'assigned_to',
        'assigned_by',
        'start_date',
        'end_date',
        'assigned_by_type',
        'assigned_by_uid',
        'is_transferred',
        'parent_assignment_id',
        'assignment_role',
        'assigned_from',
        'assigned_until',
        'is_primary',
        'status',
        'accepted_at',
        'rejected_reason',
    ];

    protected $casts = [
        'assignment_role' => TaskAssignmentRole::class,
        'status' => TaskAssignmentStatus::class,
        'assigned_from' => 'datetime',
        'assigned_until' => 'datetime',
        'accepted_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_transferred' => 'boolean',
        'is_primary' => 'boolean',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'assigned_by');
    }

    public function assignerDynamic(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'assigned_by_type', 'assigned_by_uid');
    }

    public function parentAssigner(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'parent_assignment_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Construction\Project::class, 'project_id');
    }

    public function logs()
    {
        return $this->hasMany(TaskLog::class);
    }

    public function isActive(): bool
    {
        return $this->deleted_at === null && (string) $this->status === (TaskAssignmentStatus::ACTIVE->value ?? 'active');
    }
}
