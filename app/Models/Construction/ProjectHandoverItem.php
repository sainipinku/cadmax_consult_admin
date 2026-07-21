<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectHandoverItem extends Model
{
    protected $table = 'construction_project_handover_items';

    protected $fillable = [
        'handover_id',
        'title',
        'category',
        'status',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function handover(): BelongsTo
    {
        return $this->belongsTo(ProjectHandover::class, 'handover_id');
    }
}

