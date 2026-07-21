<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveySubmission extends Model
{
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

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

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
