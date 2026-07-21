<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $table = 'construction_purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'material_id',
        'quantity',
        'unit',
        'rate',
        'tax_percent',
        'tax_amount',
        'line_total',
        'received_quantity',
    ];

    protected $casts = [
        'quantity' => 'float',
        'rate' => 'float',
        'tax_percent' => 'float',
        'tax_amount' => 'float',
        'line_total' => 'float',
        'received_quantity' => 'float',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}

