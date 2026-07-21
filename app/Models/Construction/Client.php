<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $table = 'construction_clients';

    protected $fillable = [
        'company_id',
        'client_code',
        'client_type',
        'name',
        'contact_person',
        'email',
        'phone',
        'alternate_phone',
        'gst_number',
        'billing_address',
        'site_address',
        'notes',
        'status',
        'created_by_type',
        'created_by_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
