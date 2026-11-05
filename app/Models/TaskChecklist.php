<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklist extends Model
{
    use HasFactory;

    protected $table = 'construction_task_checklists';

    protected $fillable = [
        'execution_task_id',
        'survey_plan_id',
        'day_number',
        'item_title',
        'assign_hours',
        'notes',
        'status',
        'image_url_1',
        'image_url_2',
        'is_completed',
        'completed_by_member_id',
        'completed_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'day_number' => 'integer',
        'assign_hours' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function ($model) {
            if (!empty($model->completed_by_member_id) && !\App\Models\Member::where('id', $model->completed_by_member_id)->exists()) {
                $model->completed_by_member_id = null;
            }
        });
    }

    public function getImageUrl1Attribute($value): ?string
    {
        return SurveyVisit::toFullUrl($value);
    }

    public function getImageUrl2Attribute($value): ?string
    {
        return SurveyVisit::toFullUrl($value);
    }

    public function executionTask(): BelongsTo
    {
        return $this->belongsTo(ExecutionTask::class);
    }

    public function surveyPlan(): BelongsTo
    {
        return $this->belongsTo(SurveyPlan::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'completed_by_member_id');
    }
}
