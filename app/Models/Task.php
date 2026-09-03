<?php

namespace App\Models;

use App\Enums\ClientReviewStatus;
use App\Enums\RecurringType;
use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskType;
use App\Models\Project as ConstructionProject;
use App\Models\ConstructionDocument;
use App\Observers\TaskObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\DB;
use Throwable;

class Task extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasUuids;

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'member_id',
        'start_date',
        'end_date',
        'status',
        'completed_at',
        'created_by',
        'task_type',
        'recurring_type',
        'recurring_days',
        'start_from',
        'specific_day',
        'specific_date',
        'is_stage',

        'project_id',
        'execution_plan_id',
        'survey_plan_id',
        'parent_task_id',
        'task_code',
        'priority',
        'category',
        'progress_percent',
        'requires_gps_verification',
        'planned_qty',
        'completed_qty',
        'qty_unit',
        'assigned_supervisor_member_id',
        'supervisor_approved_at',
        'approved_by_type',
        'approved_by_id',
        'client_review_status',
        'task_source',
        'created_by_type',
        'created_by_id',
        'latitude',
        'longitude',
        'sort_order',
        'client_reference',
        'deleted_by_type',
        'deleted_by_id',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'completed_at' => 'datetime',
        'start_from' => 'datetime',
        'supervisor_approved_at' => 'datetime',
        'task_type' => TaskType::class,
        'recurring_type' => RecurringType::class,
        'priority' => TaskPriority::class,
        'client_review_status' => ClientReviewStatus::class,
        'task_source' => TaskSource::class,
        'progress_percent' => 'integer',
        'planned_qty' => 'decimal:3',
        'completed_qty' => 'decimal:3',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_stage' => 'boolean',
        'requires_gps_verification' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'created_by');
    }

    public function creatorDynamic(): MorphTo
    {
        return $this->morphTo('created_by');
    }

    public function getCreatorAttribute(): Member|SuperAdmin|Admin|null
    {
        if ($this->created_by_type && $this->created_by_id) {
            try {
                $rel = $this->morphTo('created_by')->first();
                if ($rel) {
                    return $rel;
                }
            } catch (Throwable) {
            }
        }
        return $this->getRelationValue('creator');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(TaskInstance::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(TaskStage::class, 'task_id')->orderBy('order');
    }

    public function activeStage(): HasOne
    {
        return $this->hasOne(TaskStage::class)->where('is_active', true);
    }

    public function assignedMembers()
    {
        return $this->belongsToMany(Member::class, 'task_assignments', 'task_id', 'assigned_to')
            ->withTimestamps()
            ->whereNull('task_assignments.deleted_at')
            ->where(fn (Builder $q) => $q->whereNull('task_assignments.is_transferred')->orWhere('task_assignments.is_transferred', 0))
            ->withPivot([
                'uuid','assigned_by','assigned_by_type','assigned_by_uid',
                'start_date','end_date','assigned_from','assigned_until',
                'assignment_role','is_primary','status','accepted_at','rejected_reason','project_id',
            ]);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class, 'task_id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class, 'task_id')
            ->whereNull('deleted_at')
            ->where('status', 'active');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ConstructionProject::class, 'project_id');
    }

    public function executionPlan(): BelongsTo
    {
        return $this->belongsTo(ExecutionPlan::class);
    }

    public function surveyPlan(): BelongsTo
    {
        return $this->belongsTo(SurveyPlan::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assigned_supervisor_member_id');
    }

    public function assignedSupervisor(): BelongsTo
    {
        return $this->supervisor();
    }

    public function dprs(): HasMany
    {
        return $this->hasMany(DailyProgressReport::class, 'primary_task_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(ConstructionDocument::class, 'documentable');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id')->orderBy('sort_order')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'task_id')->latest('id');
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'task_id')
            ->where('kind', 'status_note')
            ->oldest('created_at');
    }

    public function latestTransition(): HasOne
    {
        return $this->hasOne(TaskComment::class, 'task_id')
            ->ofMany('id', 'max')
            ->where('kind', 'status_note');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class, 'task_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function completedChecklistItems(): HasMany
    {
        return $this->checklistItems()->where('is_completed', true);
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('task_type', 'recurring');
    }

    public function scopeOneTime(Builder $query): Builder
    {
        return $query->where('task_type', 'one_time');
    }

    public function scopeForProject(Builder $query, int|ConstructionProject|\App\Models\Construction\Project $project): Builder
    {
        $pid = is_int($project) ? $project : $project->getKey();
        return $query->where('project_id', $pid);
    }

    public function scopeAssignedTo(Builder $query, int|Member $member): Builder
    {
        $mid = is_int($member) ? $member : $member->getKey();
        return $query->whereHas('activeAssignments', fn (Builder $q) => $q->where('assigned_to', $mid));
    }

    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        $needle = '%' . $keyword . '%';
        return $query->where(static function (Builder $q) use ($needle) {
            $q->where('title', 'LIKE', $needle)
                ->orWhere('description', 'LIKE', $needle)
                ->orWhere('task_code', 'LIKE', $needle)
                ->orWhere('category', 'LIKE', $needle);
        });
    }

    public function hasAssignee(int $memberId): bool
    {
        try {
            return DB::table('task_assignments')
                ->where('task_id', $this->id)
                ->where('assigned_to', $memberId)
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    protected function checklistProgress(): Attribute
    {
        return Attribute::get(function (): float {
            $items = $this->relationLoaded('checklistItems')
                ? $this->checklistItems
                : $this->checklistItems()->get(['is_completed']);
            if ($items->isEmpty()) {
                return 0.0;
            }
            $done = $items->filter(static fn ($r) => !empty($r->is_completed))->count();
            return round(($done / $items->count()) * 100, 2);
        });
    }

    protected function assigneeSummary(): Attribute
    {
        return Attribute::get(function (): Collection {
            return $this->activeAssignments()
                ->with(['assignee:id,name,email,profile_photo_path'])
                ->get()
                ->map(static fn (TaskAssignment $a) => $a->assignee)
                ->filter()
                ->values();
        });
    }
}
