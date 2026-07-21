<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    protected $table = 'construction_purchase_requests';

    protected $fillable = [
        'project_id',
        'request_code',
        'requested_by_member_id',
        'request_date',
        'notes',
        'status',
        'reviewed_by_member_id',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'request_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'requested_by_member_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewed_by_member_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'purchase_request_id');
    }
}

