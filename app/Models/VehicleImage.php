<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class VehicleImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'image_path',
        'sort_order',
    ];

    protected $appends = [
        'image_url',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->image_path) {
                    $path = ltrim($this->image_path, '/');
                    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                        return $path;
                    }
                    if (Storage::disk('public')->exists($path)) {
                        return asset('storage/' . $path);
                    }
                    return asset('storage/' . $path);
                }
                return asset('images/common/data_not_found.png');
            }
        );
    }
}
