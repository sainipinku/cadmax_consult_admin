<?php

namespace App\Models;

use App\Models\ConstructionRole;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'construction_companies';

    protected $fillable = [
        'name',
        'legal_name',
        'email',
        'phone',
        'gst_number',
        'address',
        'logo_path',
        'settings',
        'status',
        'created_by_type',
        'created_by_id',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function superAdmin(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SuperAdmin::class, 'company_id');
    }

    public function clients(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function projects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function roles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ConstructionRole::class);
    }
}
