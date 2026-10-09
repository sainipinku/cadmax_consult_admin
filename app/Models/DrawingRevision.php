<?php

namespace App\Models;

use App\Models\ConstructionDocument;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrawingRevision extends Model
{
    protected $table = 'construction_drawing_revisions';

    protected $fillable = [
        'project_id',
        'drafting_job_id',
        'revision_no',
        'drawing_type',
        'drawing_no',
        'dwg_document_id',
        'pdf_document_id',
        'preview_image_path',
        'changes_made',
        'notes',
        'uploaded_by_member_id',
        'uploaded_at',
        'status',
        'is_locked',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'is_locked' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function draftingJob(): BelongsTo
    {
        return $this->belongsTo(DraftingJob::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'uploaded_by_member_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DrawingApproval::class);
    }

    public function dwgDocument(): BelongsTo
    {
        return $this->belongsTo(ConstructionDocument::class, 'dwg_document_id');
    }

    public function pdfDocument(): BelongsTo
    {
        return $this->belongsTo(ConstructionDocument::class, 'pdf_document_id');
    }
}
