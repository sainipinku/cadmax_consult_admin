<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyPlanMember extends Model
{
    protected $table = 'construction_survey_plan_members';

    protected $fillable = [
        'survey_plan_id',
        'member_id',
        'role_in_survey',
        'status',
    ];

    public function surveyPlan(): BelongsTo
    {
        return $this->belongsTo(SurveyPlan::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
