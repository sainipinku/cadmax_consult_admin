<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DraftingJob extends Model
{
    protected $table = 'construction_drafting_jobs';

    protected $fillable = [
        'project_id',
        'survey_submission_id',
        'assigned_to_member_id',
        'assigned_by_type',
        'assigned_by_id',
        'assigned_at',
        'due_date',
        'status',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'due_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function surveySubmission(): BelongsTo
    {
        return $this->belongsTo(SurveySubmission::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assigned_to_member_id');
    }

    public function assignedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function drawingRevisions(): HasMany
    {
        return $this->hasMany(DrawingRevision::class);
    }
}
