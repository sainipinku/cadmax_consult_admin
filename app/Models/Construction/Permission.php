<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $table = 'construction_permissions';

    protected $fillable = [
        'name',
        'slug',
        'module',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'construction_role_permissions',
            'permission_id',
            'role_id'
        )->withTimestamps();
    }
}
