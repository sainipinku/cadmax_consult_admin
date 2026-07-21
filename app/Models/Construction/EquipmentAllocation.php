<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EquipmentAllocation extends Model
{
    protected $table = 'construction_equipment_allocations';

    protected $fillable = [
        'project_id',
        'equipment_id',
        'assigned_to_member_id',
        'allocated_at',
        'allocate_latitude',
        'allocate_longitude',
        'allocate_gps_accuracy_meters',
        'allocate_gps_verified',
        'returned_at',
        'return_latitude',
        'return_longitude',
        'return_gps_accuracy_meters',
        'return_gps_verified',
        'status',
        'notes',
        'allocated_by_type',
        'allocated_by_id',
        'returned_by_type',
        'returned_by_id',
    ];

    protected $casts = [
        'allocated_at' => 'datetime',
        'returned_at' => 'datetime',
        'allocate_latitude' => 'float',
        'allocate_longitude' => 'float',
        'allocate_gps_accuracy_meters' => 'float',
        'allocate_gps_verified' => 'boolean',
        'return_latitude' => 'float',
        'return_longitude' => 'float',
        'return_gps_accuracy_meters' => 'float',
        'return_gps_verified' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assigned_to_member_id');
    }

    public function allocatedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function returnedBy(): MorphTo
    {
        return $this->morphTo();
    }
}

