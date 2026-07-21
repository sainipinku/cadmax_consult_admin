<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'uuid',
        'member_id',
        'employee_id',
        'alternate_number',
        'aadhaar_number',
        'pan_number',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public static function boot(): void
    {
        parent::boot();
        static::creating(function ($v) {
            if (empty($v->employee_id)) {
                $v->employee_id = static::generateEmployeeId();
            }
        });
    }

    public static function generateEmployeeId(): string
    {
        $prefix = 'EMP-';
        $lastEmployee = static::withTrashed()
            ->where('employee_id', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(employee_id, 5) AS UNSIGNED) DESC')
            ->first();

        if ($lastEmployee) {
            $lastNumber = (int) substr($lastEmployee->employee_id, 4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get profile photo from the related Member
     */
    public function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->member?->profile_photo_url ?? asset('images/profileimg.png');
            }
        );
    }
}