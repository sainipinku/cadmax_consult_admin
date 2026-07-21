<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DrawingApproval extends Model
{
    protected $table = 'construction_drawing_approvals';

    protected $fillable = [
        'project_id',
        'drawing_revision_id',
        'requested_by_type',
        'requested_by_id',
        'requested_at',
        'approved_by_type',
        'approved_by_id',
        'approved_at',
        'decision',
        'remarks',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function drawingRevision(): BelongsTo
    {
        return $this->belongsTo(DrawingRevision::class);
    }

    public function requestedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function approvedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
