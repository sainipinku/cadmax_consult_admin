<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleLocationPing extends Model
{
    protected $table = 'construction_vehicle_location_pings';

    protected $fillable = [
        'project_id',
        'vehicle_id',
        'reported_by_member_id',
        'recorded_at',
        'latitude',
        'longitude',
        'gps_accuracy_meters',
        'speed_kmph',
        'heading_degrees',
        'odometer_km',
        'gps_verified',
        'source',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'gps_accuracy_meters' => 'float',
        'speed_kmph' => 'float',
        'heading_degrees' => 'float',
        'odometer_km' => 'float',
        'gps_verified' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reported_by_member_id');
    }
}

