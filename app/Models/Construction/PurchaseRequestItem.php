<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    protected $table = 'construction_purchase_request_items';

    protected $fillable = [
        'purchase_request_id',
        'material_id',
        'quantity',
        'unit',
        'estimated_rate',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'estimated_rate' => 'float',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}

