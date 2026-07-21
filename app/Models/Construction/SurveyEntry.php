<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyEntry extends Model
{
    protected $table = 'construction_survey_entries';

    protected $fillable = [
        'project_id',
        'survey_visit_id',
        'entry_type',
        'title',
        'description',
        'supporting_document_id',
        'captured_by_member_id',
        'captured_at',
        'sort_order',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function surveyVisit(): BelongsTo
    {
        return $this->belongsTo(SurveyVisit::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'captured_by_member_id');
    }

    public function supportingDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'supporting_document_id');
    }
}
