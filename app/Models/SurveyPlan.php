<?php

namespace App\Models;

use App\Support\Construction\SurveyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SurveyPlan extends Model
{
    public const STATUS_DRAFT = SurveyStatus::DRAFT;

    public const STATUS_PLANNED = SurveyStatus::PLANNED;

    public const STATUS_IN_PROGRESS = SurveyStatus::IN_PROGRESS;

    public const STATUS_SUBMITTED = SurveyStatus::SUBMITTED;

    public const STATUS_APPROVED = SurveyStatus::APPROVED;

    public const STATUS_REVISION_REQUESTED = SurveyStatus::REVISION_REQUESTED;

    public const STATUS_REJECTED = SurveyStatus::REJECTED;

    public const MANUALLY_MANAGEABLE_STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_IN_PROGRESS,
    ];

    protected $table = 'construction_survey_plans';

    protected $fillable = [
        'project_id',
        'survey_code',
        'title',
        'description',
        'site_address',
        'site_latitude',
        'site_longitude',
        'planned_date',
        'planned_start_time',
        'planned_end_time',
        'assigned_by_type',
        'assigned_by_id',
        'status',
    ];

    protected $appends = [
        'status_key',
        'status_label',
    ];

    protected $casts = [
        'site_latitude' => 'float',
        'site_longitude' => 'float',
        'planned_date' => 'date',
        'status' => 'integer',
    ];

    public function getStatusKeyAttribute(): string
    {
        return SurveyStatus::key((int) $this->status);
    }

    public function getStatusLabelAttribute(): string
    {
        return SurveyStatus::label((int) $this->status);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function planMembers(): HasMany
    {
        return $this->hasMany(SurveyPlanMember::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(SurveyVisit::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(
            ConstructionDocument::class,
            'documentable'
        );
    }
}