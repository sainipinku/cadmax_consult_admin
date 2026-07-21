<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInvoiceItem extends Model
{
    protected $table = 'construction_client_invoice_items';

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit',
        'rate',
        'line_subtotal',
        'gst_percent',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'line_total_tax',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'float',
        'rate' => 'float',
        'line_subtotal' => 'float',
        'gst_percent' => 'float',
        'cgst_amount' => 'float',
        'sgst_amount' => 'float',
        'igst_amount' => 'float',
        'line_total_tax' => 'float',
        'line_total' => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'invoice_id');
    }
}

