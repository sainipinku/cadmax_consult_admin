<?php

namespace App\Models;

use App\Models\Project;
use App\Models\VehicleAssignment;
use App\Models\VehicleLocationPing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConstructionVehicle extends Model
{
    use SoftDeletes;

    protected $table = 'construction_vehicles';

    protected $fillable = [
        'project_id',
        'vehicle_code',
        'registration_number',
        'vehicle_type',
        'make',
        'model',
        'status',
        'created_by_type',
        'created_by_id',
    ];

    protected $appends = [
        'vehicle_id',
        'vehicle_number',
        'vehicle_name',
    ];

    public static function generateVehicleCode(string $prefix = 'VEH-'): string
    {
        $last = static::withTrashed()
            ->where('vehicle_code', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(vehicle_code, ' . (strlen($prefix) + 1) . ') AS UNSIGNED) DESC')
            ->first();

        $maxNum = 0;
        if ($last && preg_match('/' . preg_quote($prefix, '/') . '(\d+)/', (string) $last->vehicle_code, $matches)) {
            $maxNum = (int) $matches[1];
        }

        $nextNum = max($maxNum + 1, (static::withTrashed()->max('id') ?? 0) + 1);

        do {
            $candidate = $prefix . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);
            $nextNum++;
        } while (static::withTrashed()->where('vehicle_code', $candidate)->exists());

        return $candidate;
    }

    public function getVehicleIdAttribute(): string
    {
        return $this->attributes['vehicle_code'] ?? ($this->id ? 'VHC-' . $this->id : '');
    }

    public function getVehicleNumberAttribute(): string
    {
        return $this->attributes['registration_number'] ?? '';
    }

    public function getVehicleNameAttribute(): ?string
    {
        $make = trim((string) ($this->make ?? ''));
        $model = trim((string) ($this->model ?? ''));

        if (!empty($make) && !empty($model)) {
            if (strcasecmp($make, $model) === 0) {
                return $model;
            }
            if (str_contains(strtolower($model), strtolower($make))) {
                return $model;
            }
            return $make . ' ' . $model;
        }

        return $make ?: ($model ?: null);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForActor($query, ?Model $actor)
    {
        if (!$actor) {
            return $query;
        }

        if ($actor instanceof SuperAdmin) {
            if ((int) $actor->id === 1 || (string) $actor->id === '1' || (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1)) {
                return $query;
            }

            $companyId = $actor->company_id;
            return $query->where(function ($q) use ($actor, $companyId) {
                if ($companyId) {
                    $q->whereHas('project', function ($pq) use ($companyId, $actor) {
                        $pq->where('company_id', $companyId)
                           ->orWhere(function ($sq) use ($actor) {
                               $sq->where('created_by_type', SuperAdmin::class)
                                  ->where('created_by_id', $actor->id);
                           });
                    })->orWhere(function ($sq) use ($actor) {
                        $sq->where('created_by_type', $actor::class)
                           ->where('created_by_id', $actor->id);
                    });
                } else {
                    $q->where(function ($sq) use ($actor) {
                        $sq->where('created_by_type', $actor::class)
                           ->where('created_by_id', $actor->id)
                           ->orWhereHas('project', function ($pq) use ($actor) {
                               $pq->where('created_by_type', SuperAdmin::class)
                                  ->where('created_by_id', $actor->id);
                           });
                    });
                }
            });
        }

        if ($actor instanceof Member) {
            $companyId = $actor->company_id;
            return $query->whereHas('project', function ($pq) use ($companyId, $actor) {
                if ($companyId) {
                    $pq->where('company_id', $companyId);
                }
                if ($actor->created_by) {
                    $pq->orWhere('created_by_id', $actor->created_by);
                }
            });
        }

        return $query;
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class, 'vehicle_id');
    }

    public function locationPings(): HasMany
    {
        return $this->hasMany(VehicleLocationPing::class, 'vehicle_id');
    }
}

