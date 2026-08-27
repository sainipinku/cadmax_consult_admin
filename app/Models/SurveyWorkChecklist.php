<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SurveyWorkChecklist extends Model
{
    public const SOURCE_SUPER_ADMIN = 1;

    public const SOURCE_ADMIN = 2;

    public const SOURCE_MEMBER = 3;

    public const STATUS_PENDING = 0;

    public const STATUS_COMPLETED = 1;

    public const VALID_SOURCES = [
        self::SOURCE_SUPER_ADMIN,
        self::SOURCE_ADMIN,
        self::SOURCE_MEMBER,
    ];

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
    ];

    private const SOURCE_KEYS = [
        self::SOURCE_SUPER_ADMIN => 'super_admin',
        self::SOURCE_ADMIN => 'admin',
        self::SOURCE_MEMBER => 'member',
    ];

    private const SOURCE_LABELS = [
        self::SOURCE_SUPER_ADMIN => 'Super Admin',
        self::SOURCE_ADMIN => 'Admin',
        self::SOURCE_MEMBER => 'Member',
    ];

    private const STATUS_KEYS = [
        self::STATUS_PENDING => 'pending',
        self::STATUS_COMPLETED => 'completed',
    ];

    private const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_COMPLETED => 'Completed',
    ];

    protected $table = 'construction_survey_work_checklists';

    protected $fillable = [
        'survey_plan_member_id',
        'work_title',
        'source',
        'status',
        'added_by_type',
        'added_by_id',
        'completed_by_member_id',
        'completed_at',
        'sort_order',
        'client_reference',
    ];

    protected $appends = [
        'source_key',
        'source_label',
        'status_key',
        'status_label',
        'is_completed',
    ];

    protected $casts = [
        'survey_plan_member_id' => 'integer',
        'source' => 'integer',
        'status' => 'integer',
        'added_by_id' => 'integer',
        'completed_by_member_id' => 'integer',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function surveyPlanMember(): BelongsTo
    {
        return $this->belongsTo(
            SurveyPlanMember::class,
            'survey_plan_member_id'
        );
    }

    public function addedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(
            Member::class,
            'completed_by_member_id'
        );
    }

    public function scopeForAssignment(
        Builder $query,
        int $surveyPlanMemberId
    ): Builder {
        return $query->where(
            'survey_plan_member_id',
            $surveyPlanMemberId
        );
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getSourceKeyAttribute(): string
    {
        return self::SOURCE_KEYS[(int) $this->source] ?? 'unknown';
    }

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCE_LABELS[(int) $this->source] ?? 'Unknown';
    }

    public function getStatusKeyAttribute(): string
    {
        return self::STATUS_KEYS[(int) $this->status] ?? 'unknown';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[(int) $this->status] ?? 'Unknown';
    }

    public function getIsCompletedAttribute(): bool
    {
        return (int) $this->status === self::STATUS_COMPLETED;
    }
}