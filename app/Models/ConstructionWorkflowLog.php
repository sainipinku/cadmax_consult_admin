<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ConstructionWorkflowLog extends Model
{
    protected $table = 'construction_workflow_logs';

    protected $fillable = [
        'project_id',
        'stage',
        'status',
        'action',
        'action_by_type',
        'action_by_id',
        'remarks',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function actionBy(): MorphTo
    {
        return $this->morphTo();
    }
}
