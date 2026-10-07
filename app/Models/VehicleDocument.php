<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class VehicleDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'document_type',
        'document_name',
        'file_path',
        'file_type',
    ];

    protected $appends = [
        'file_url',
        'is_image',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function fileUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->file_path) {
                    $path = ltrim($this->file_path, '/');
                    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                        return $path;
                    }
                    return asset('storage/' . $path);
                }
                return asset('images/common/data_not_found.png');
            }
        );
    }

    public function isImage(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->file_path) return false;
                $ext = strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION));
                return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
            }
        );
    }
}
