<?php

namespace App\Models\Construction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'construction_equipments';

    protected $fillable = [
        'project_id',
        'equipment_code',
        'name',
        'equipment_type',
        'serial_number',
        'status',
        'created_by_type',
        'created_by_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(EquipmentAllocation::class, 'equipment_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(EquipmentUsageLog::class, 'equipment_id');
    }
}

