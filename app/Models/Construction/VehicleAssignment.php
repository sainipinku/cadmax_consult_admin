<?php

namespace App\Models\Construction;

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
    ];

    protected $casts = [
        'assigned_from' => 'datetime',
        'assigned_to' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
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

