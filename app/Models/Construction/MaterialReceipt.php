<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialReceipt extends Model
{
    protected $table = 'construction_material_receipts';

    protected $fillable = [
        'project_id',
        'purchase_order_id',
        'receipt_code',
        'received_by_member_id',
        'received_at',
        'latitude',
        'longitude',
        'gps_accuracy_meters',
        'status',
        'notes',
        'receipt_document_id',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'gps_accuracy_meters' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'received_by_member_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialReceiptItem::class, 'material_receipt_id');
    }

    public function receiptDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'receipt_document_id');
    }
}
