<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ClientPayment extends Model
{
    protected $table = 'construction_client_payments';

    protected $fillable = [
        'project_id',
        'invoice_id',
        'payment_code',
        'received_at',
        'amount',
        'method',
        'reference_no',
        'notes',
        'received_by_type',
        'received_by_id',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'amount' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'invoice_id');
    }

    public function receivedBy(): MorphTo
    {
        return $this->morphTo();
    }
}

