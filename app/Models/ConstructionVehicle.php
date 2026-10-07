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
        if (!empty($this->make) && !empty($this->model)) {
            return $this->make . ' ' . $this->model;
        }
        return $this->make ?: $this->model;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
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

