<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SurveyVisit extends Model
{
    protected $table = 'construction_survey_visits';

    protected $fillable = [
        'project_id',
        'survey_plan_id',
        'checked_in_by_member_id',
        'check_in_at',
        'check_in_latitude',
        'check_in_longitude',
        'gps_distance_meters',
        'gps_verified',
        'status',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_in_latitude' => 'float',
        'check_in_longitude' => 'float',
        'gps_distance_meters' => 'float',
        'gps_verified' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function surveyPlan(): BelongsTo
    {
        return $this->belongsTo(SurveyPlan::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'checked_in_by_member_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SurveyEntry::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(SurveyMeasurement::class);
    }

    public function submission(): HasOne
    {
        return $this->hasOne(SurveySubmission::class);
    }
}
