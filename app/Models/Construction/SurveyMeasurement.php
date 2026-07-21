<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyMeasurement extends Model
{
    protected $table = 'construction_survey_measurements';

    protected $fillable = [
        'project_id',
        'survey_visit_id',
        'area_name',
        'measurement_type',
        'length',
        'width',
        'height',
        'unit',
        'quantity',
        'notes',
        'captured_by_member_id',
    ];

    protected $casts = [
        'length' => 'float',
        'width' => 'float',
        'height' => 'float',
        'quantity' => 'float',
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
}
