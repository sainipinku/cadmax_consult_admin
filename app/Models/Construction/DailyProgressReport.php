<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyProgressReport extends Model
{
    protected $table = 'construction_daily_progress_reports';

    protected $fillable = [
        'project_id',
        'execution_task_id',
        'report_date',
        'submitted_by_member_id',
        'submitted_at',
        'summary',
        'work_completed',
        'blockers',
        'workforce_count',
        'latitude',
        'longitude',
        'gps_accuracy_meters',
        'weather_summary',
        'supporting_document_id',
        'reviewed_by_member_id',
        'reviewed_at',
        'review_notes',
        'status',
    ];

    protected $casts = [
        'report_date' => 'date',
        'submitted_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'gps_accuracy_meters' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function executionTask(): BelongsTo
    {
        return $this->belongsTo(ExecutionTask::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'submitted_by_member_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewed_by_member_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DailyProgressItem::class, 'daily_progress_report_id');
    }

    public function supportingDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'supporting_document_id');
    }
}
