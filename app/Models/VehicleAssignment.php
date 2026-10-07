<?php

namespace App\Models;

use App\Models\ConstructionVehicle;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VehicleAssignment extends Model
{
    protected $table = 'construction_vehicle_assignments';

    protected $fillable = [
        'project_id',
        'vehicle_id',
        'driver_member_id',
        'assigned_from',
        'assigned_to',
        'status',
        'assigned_by_type',
        'assigned_by_id',
        'assignment_type',
        'from_location',
        'from_lat',
        'from_lng',
        'to_location',
        'to_lat',
        'to_lng',
        'material_list',
        'daily_checkpoint_required',
        'notes',
    ];

    protected $casts = [
        'assigned_from' => 'datetime',
        'assigned_to' => 'datetime',
        'material_list' => 'array',
        'daily_checkpoint_required' => 'boolean',
        'from_lat' => 'float',
        'from_lng' => 'float',
        'to_lat' => 'float',
        'to_lng' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(ConstructionVehicle::class, 'vehicle_id')->withDefault(function () {
            return new ConstructionVehicle([
                'vehicle_code' => 'N/A',
                'registration_number' => 'N/A',
            ]);
        });
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'driver_member_id');
    }

    public function assignedBy(): MorphTo
    {
        return $this->morphTo();
    }
}

