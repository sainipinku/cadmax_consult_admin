<?php

namespace App\Models\Construction;

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
        'dwg_document_id',
        'pdf_document_id',
        'notes',
        'uploaded_by_member_id',
        'uploaded_at',
        'status',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
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
        return $this->belongsTo(Document::class, 'dwg_document_id');
    }

    public function pdfDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'pdf_document_id');
    }
}
