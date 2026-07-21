<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProjectBudget extends Model
{
    protected $table = 'construction_project_budgets';

    protected $fillable = [
        'project_id',
        'version_no',
        'estimated_amount',
        'approved_amount',
        'currency',
        'notes',
        'submitted_by_type',
        'submitted_by_id',
        'approved_by_type',
        'approved_by_id',
        'approved_at',
        'status',
    ];

    protected $casts = [
        'estimated_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submittedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function approvedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
