<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SurveyPlan extends Model
{
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

    protected $casts = [
        'site_latitude' => 'float',
        'site_longitude' => 'float',
        'planned_date' => 'date',
    ];

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
}
