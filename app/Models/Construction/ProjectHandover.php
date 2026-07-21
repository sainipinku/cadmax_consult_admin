<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectHandover extends Model
{
    use SoftDeletes;

    protected $table = 'construction_project_handovers';

    protected $fillable = [
        'project_id',
        'handover_code',
        'planned_handover_date',
        'actual_handover_at',
        'closure_date',
        'status',
        'client_signatory_name',
        'client_signatory_role',
        'signoff_notes',
        'final_document_id',
        'created_by_type',
        'created_by_id',
        'handed_over_by_type',
        'handed_over_by_id',
        'closed_by_type',
        'closed_by_id',
    ];

    protected $casts = [
        'planned_handover_date' => 'date',
        'actual_handover_at' => 'datetime',
        'closure_date' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function finalDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'final_document_id');
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function handedOverBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function closedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectHandoverItem::class, 'handover_id');
    }
}

