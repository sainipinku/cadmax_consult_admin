<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyProgressItem extends Model
{
    protected $table = 'construction_daily_progress_items';

    protected $fillable = [
        'project_id',
        'daily_progress_report_id',
        'execution_task_id',
        'title',
        'description',
        'unit',
        'planned_quantity',
        'completed_quantity',
        'percent_complete',
        'remarks',
    ];

    protected $casts = [
        'planned_quantity' => 'float',
        'completed_quantity' => 'float',
        'percent_complete' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyProgressReport::class, 'daily_progress_report_id');
    }

    public function executionTask(): BelongsTo
    {
        return $this->belongsTo(ExecutionTask::class);
    }
}
