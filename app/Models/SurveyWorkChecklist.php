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

    protected $table =
        'construction_survey_work_checklists';

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

    public function scopePending(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_PENDING
        );
    }

    public function scopeCompleted(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_COMPLETED
        );
    }

    public function scopeOrdered(
        Builder $query
    ): Builder {
        return $query
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}