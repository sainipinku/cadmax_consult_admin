<?php

namespace App\Models;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Construction\SurveyStatus;

class SurveySubmission extends Model
{



public const STATUS_DRAFT = SurveyStatus::DRAFT;

public const STATUS_SUBMITTED = SurveyStatus::SUBMITTED;

public const STATUS_APPROVED = SurveyStatus::APPROVED;

public const STATUS_REVISION_REQUESTED = SurveyStatus::REVISION_REQUESTED;

public const STATUS_REJECTED = SurveyStatus::REJECTED;




    protected $table = 'construction_survey_submissions';

    protected $fillable = [
        'project_id',
        'survey_visit_id',
        'submitted_by_member_id',
        'submitted_at',
        'status',
        'review_notes',
        'reviewed_by_member_id',
        'reviewed_at',
    ];

    protected $appends = [
    'status_key',
    'status_label',
];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
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

    public function surveyVisit(): BelongsTo
    {
        return $this->belongsTo(SurveyVisit::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'submitted_by_member_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewed_by_member_id');
    }

    public function draftingJobs(): HasMany
    {
        return $this->hasMany(DraftingJob::class);
    }
}
