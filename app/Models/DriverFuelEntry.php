<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverFuelEntry extends Model
{
    use HasFactory;

    protected $table = 'driver_fuel_entries';

    protected $fillable = [
        'driver_member_id',
        'company_id',
        'project_id',
        'vehicle_id',
        'vehicle_number',
        'fuel_type',
        'quantity_liters',
        'total_amount',
        'fuel_date',
        'odometer_reading',
        'receipt_photo_url',
        'notes',
        'status',
        'reviewed_by',
        'reviewed_by_type',
        'reviewed_at',
        'admin_remarks',
    ];

    protected $casts = [
        'fuel_date' => 'date',
        'quantity_liters' => 'float',
        'total_amount' => 'float',
        'odometer_reading' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'driver_member_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(ConstructionVehicle::class, 'vehicle_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}

