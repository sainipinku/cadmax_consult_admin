<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientInvoice extends Model
{
    use SoftDeletes;

    protected $table = 'construction_client_invoices';

    protected $fillable = [
        'project_id',
        'invoice_code',
        'invoice_date',
        'due_date',
        'tax_type',
        'status',
        'notes',
        'subtotal_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_tax_amount',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'created_by_type',
        'created_by_id',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal_amount' => 'float',
        'cgst_amount' => 'float',
        'sgst_amount' => 'float',
        'igst_amount' => 'float',
        'total_tax_amount' => 'float',
        'total_amount' => 'float',
        'paid_amount' => 'float',
        'balance_amount' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClientInvoiceItem::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class, 'invoice_id');
    }
}

