<?php

namespace App\Models;

use App\Enums\TaskChecklistSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskChecklistItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'task_checklist_items';

    protected $fillable = [
        'task_id',
        'project_id',
        'day_number',
        'item_title',
        'is_completed',
        'sort_order',
        'completed_by_member_id',
        'completed_at',
        'source',
        'client_reference',
        'created_by_type',
        'created_by_id',
        'updated_by_type',
        'updated_by_id',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'sort_order' => 'integer',
        'day_number' => 'integer',
        'completed_at' => 'datetime',
        'source' => TaskChecklistSource::class,
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Construction\Project::class, 'project_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'completed_by_member_id');
    }

    public function createdBy()
    {
        return $this->morphTo(__FUNCTION__, 'created_by_type', 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->morphTo(__FUNCTION__, 'updated_by_type', 'updated_by_id');
    }

    public function toggleComplete(bool $completed, ?Member $member = null): void
    {
        $this->is_completed = $completed;
        if ($completed) {
            $this->completed_at = now();
            $this->completed_by_member_id = $member?->id ?? $this->completed_by_member_id;
        } else {
            $this->completed_at = null;
            $this->completed_by_member_id = null;
        }
    }
}
