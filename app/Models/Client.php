<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $table = 'construction_clients';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($client) {
            if (empty($client->client_code) || static::withTrashed()->where('client_code', $client->client_code)->exists()) {
                $client->client_code = static::generateUniqueClientCode($client->client_code);
            }
        });
    }

    public static function generateUniqueClientCode(?string $preferredCode = null): string
    {
        if (!empty($preferredCode)) {
            $exists = static::withTrashed()->where('client_code', $preferredCode)->exists();
            if (!$exists) {
                return $preferredCode;
            }
        }

        $baseNumber = (int) (static::withTrashed()->max('id') ?? 0) + 1;

        $latestCode = static::withTrashed()
            ->where(function ($q) {
                $q->where('client_code', 'LIKE', 'CLI-%')
                    ->orWhere('client_code', 'LIKE', 'CLT-%');
            })
            ->orderByRaw("CAST(SUBSTRING(client_code, 5) AS UNSIGNED) DESC")
            ->value('client_code');

        if ($latestCode && preg_match('/(?:CLI|CLT)-(\d+)/', $latestCode, $matches)) {
            $highestCodeNum = (int) $matches[1];
            if ($highestCodeNum >= $baseNumber) {
                $baseNumber = $highestCodeNum + 1;
            }
        }

        do {
            $candidateCode = 'CLI-' . str_pad((string) $baseNumber, 5, '0', STR_PAD_LEFT);
            $exists = static::withTrashed()->where('client_code', $candidateCode)->exists();
            if ($exists) {
                $baseNumber++;
            }
        } while ($exists);

        return $candidateCode;
    }

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
        'client_company_name',
        'client_designation',
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
