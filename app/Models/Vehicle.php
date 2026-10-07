<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'uuid',
        'vehicle_id',
        'vehicle_type',
        'vehicle_number',
        'vehicle_name',
        'brand',
        'fuel_type',
        'color',
        'manufacturing_year',
        'engine_number',
        'chassis_number',
        'purchase_date',
        'purchase_amount',
        'current_km_reading',
        'vehicle_image',
        'status',

        'insurance_provider',
        'policy_number',
        'insurance_type',
        'insurance_start_date',
        'insurance_end_date',
        'insurance_status',

        'puc_certificate_number',
        'puc_issue_date',
        'puc_expiry_date',
        'puc_status',

        'challan_number',
        'challan_date',
        'violation_type',
        'fine_amount',
        'payment_status',
    ];

    protected $appends = [
        'vehicle_image_url',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_amount' => 'decimal:2',
        'insurance_start_date' => 'date',
        'insurance_end_date' => 'date',
        'puc_issue_date' => 'date',
        'puc_expiry_date' => 'date',
        'challan_date' => 'date',
        'fine_amount' => 'decimal:2',
        'status' => 'integer',
        'insurance_status' => 'integer',
        'puc_status' => 'integer',
        'payment_status' => 'integer',
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        static::creating(function ($v) {
            if (empty($v->vehicle_id)) {
                $v->vehicle_id = static::generateVehicleId();
            }
            if ($v->insurance_end_date) {
                $v->insurance_status = now()->lessThanOrEqualTo($v->insurance_end_date) ? 1 : 0;
            }
            if ($v->puc_expiry_date) {
                $v->puc_status = now()->lessThanOrEqualTo($v->puc_expiry_date) ? 1 : 0;
            }
        });
        static::updating(function ($v) {
            if ($v->insurance_end_date) {
                $v->insurance_status = now()->lessThanOrEqualTo($v->insurance_end_date) ? 1 : 0;
            }
            if ($v->puc_expiry_date) {
                $v->puc_status = now()->lessThanOrEqualTo($v->puc_expiry_date) ? 1 : 0;
            }
        });
    }

    public static function generateVehicleId(): string
    {
        $prefix = 'VHC-';
        $lastVehicle = static::withTrashed()
            ->where('vehicle_id', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(vehicle_id, 5) AS UNSIGNED) DESC')
            ->first();

        if ($lastVehicle) {
            $lastNumber = (int) substr($lastVehicle->vehicle_id, 4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    public function images()
    {
        return $this->hasMany(VehicleImage::class, 'vehicle_id')->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    public function documents()
    {
        return $this->hasMany(VehicleDocument::class, 'vehicle_id')->orderBy('id', 'asc');
    }

    public function vehicleImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->vehicle_image) {
                    $path = ltrim($this->vehicle_image, '/');
                    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                        return $path;
                    }
                    if (Storage::disk('public')->exists($path)) {
                        return asset('storage/' . $path);
                    }
                }
                if ($this->relationLoaded('images') && $this->images->first()) {
                    return $this->images->first()->image_url;
                }
                $firstImg = $this->images()->first();
                if ($firstImg) {
                    return $firstImg->image_url;
                }
                return asset('images/common/data_not_found.png');
            }
        );
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            0 => 'Active',
            1 => 'Inactive',
            2 => 'Sold',
            default => 'Unknown',
        };
    }

    public function getInsuranceStatusLabelAttribute(): string
    {
        if ($this->insurance_status === null) {
            return 'N/A';
        }
        return $this->insurance_status === 1 ? 'Active' : 'Expired';
    }

    public function getPucStatusLabelAttribute(): string
    {
        if ($this->puc_status === null) {
            return 'N/A';
        }
        return $this->puc_status === 1 ? 'Valid' : 'Expired';
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        if ($this->payment_status === null) {
            return 'N/A';
        }
        return $this->payment_status === 1 ? 'Paid' : 'Unpaid';
    }

    public function scopeNewestFirst($query)
    {
        return $query->orderByRaw("CAST(SUBSTRING(vehicle_id, 5) AS UNSIGNED) DESC");
    }

    public function scopeOldestFirst($query)
    {
        return $query->orderByRaw("CAST(SUBSTRING(vehicle_id, 5) AS UNSIGNED) ASC");
    }

    public function scopeSortByVehicleNumber($query)
    {
        return $query->orderBy('vehicle_number', 'asc');
    }
}