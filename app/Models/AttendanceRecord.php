<?php

namespace App\Models;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $table = 'construction_attendance_records';

    protected $fillable = [
        'project_id',
        'execution_task_id',
        'member_id',
        'attendance_date',
        'check_in_at',
        'check_out_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'gps_accuracy_meters',
        'attendance_type',
        'notes',
        'reviewed_by_member_id',
        'reviewed_at',
        'review_notes',
        'status',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'check_in_latitude' => 'float',
        'check_in_longitude' => 'float',
        'check_out_latitude' => 'float',
        'check_out_longitude' => 'float',
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

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewed_by_member_id');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
