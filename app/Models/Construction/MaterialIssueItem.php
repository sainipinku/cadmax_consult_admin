<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialIssueItem extends Model
{
    protected $table = 'construction_material_issue_items';

    protected $fillable = [
        'material_issue_id',
        'material_id',
        'execution_task_id',
        'quantity',
        'unit',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(MaterialIssue::class, 'material_issue_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function executionTask(): BelongsTo
    {
        return $this->belongsTo(ExecutionTask::class, 'execution_task_id');
    }
}

