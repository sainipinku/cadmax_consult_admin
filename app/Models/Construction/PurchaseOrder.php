<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PurchaseOrder extends Model
{
    protected $table = 'construction_purchase_orders';

    protected $fillable = [
        'project_id',
        'purchase_request_id',
        'po_code',
        'vendor_id',
        'po_date',
        'expected_delivery_date',
        'status',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'invoice_document_id',
        'created_by_type',
        'created_by_id',
    ];

    protected $casts = [
        'po_date' => 'date',
        'expected_delivery_date' => 'date',
        'subtotal_amount' => 'float',
        'tax_amount' => 'float',
        'total_amount' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function invoiceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'invoice_document_id');
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(MaterialReceipt::class, 'purchase_order_id');
    }
}

