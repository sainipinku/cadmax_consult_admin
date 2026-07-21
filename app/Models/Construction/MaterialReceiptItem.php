<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialReceiptItem extends Model
{
    protected $table = 'construction_material_receipt_items';

    protected $fillable = [
        'material_receipt_id',
        'material_id',
        'quantity',
        'unit',
        'rate',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'float',
        'rate' => 'float',
        'line_total' => 'float',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(MaterialReceipt::class, 'material_receipt_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}

