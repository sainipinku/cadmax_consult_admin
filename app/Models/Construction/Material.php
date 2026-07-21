<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use SoftDeletes;

    protected $table = 'construction_materials';

    protected $fillable = [
        'project_id',
        'material_code',
        'name',
        'unit',
        'default_rate',
        'status',
    ];

    protected $casts = [
        'default_rate' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MaterialStock::class, 'material_id');
    }
}

