<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStock extends Model
{
    protected $table = 'construction_material_stocks';

    protected $fillable = [
        'project_id',
        'material_id',
        'on_hand_quantity',
    ];

    protected $casts = [
        'on_hand_quantity' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}

