<?php

namespace App\Models;

use App\Models\EquipmentAllocation;
use App\Models\EquipmentUsageLog;
use App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConstructionEquipment extends Model
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

    public function scopeForActor($query, ?\Illuminate\Database\Eloquent\Model $actor)
    {
        if (!$actor) {
            return $query;
        }

        if ($actor instanceof SuperAdmin) {
            $companyId = $actor->company_id;
            return $query->whereHas('project', function ($q) use ($actor, $companyId) {
                if ($companyId) {
                    $q->where('company_id', $companyId)
                      ->orWhere('created_by_id', $actor->id);
                } else {
                    $q->where('created_by_id', $actor->id);
                }
            });
        }

        return $query;
    }
}

