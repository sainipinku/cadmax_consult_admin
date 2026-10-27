<?php

namespace App\Models;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Equipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'equipments';

    protected $fillable = [
        'created_by',
        'company_id',
        'equipment_id',
        'category_id',
        'equipment_name',
        'company',
        'quantity',
        'ownership_type',
        'location_site',
        'notes',
        'brand',
        'model',
        'serial_number',
        'asset_tag',
        'purchase_date',
        'purchase_cost',
        'vendor',
        'warranty_till',
        'photo',
        'status',
        'assigned_employee_id',
        'assigned_project_id',
        'assigned_date',
    ];

    protected $appends = [
        'photo_url',
    ];

    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'warranty_till' => 'date:Y-m-d',
        'assigned_date' => 'date:Y-m-d',
        'purchase_cost' => 'decimal:2',
        'status' => 'integer',
        'quantity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Equipment $equipment) {
            if (empty($equipment->equipment_id)) {
                $equipment->equipment_id = static::generateEquipmentId();
            }
        });
    }

    public static function generateEquipmentId(): string
    {
        $prefix = 'EQ-';

        do {
            $random = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $equipmentId = $prefix . $random;
        } while (static::where('equipment_id', $equipmentId)->exists());

        return $equipmentId;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function assignedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'assigned_project_id');
    }

    public function photoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->photo && Storage::disk('public')->exists($this->photo)) {
                    return Storage::disk('public')->url($this->photo);
                }

                return asset('images/common/data_not_found.png');
            }
        );
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByRaw("CAST(SUBSTRING(equipment_id, 4) AS UNSIGNED) DESC");
    }

    public function scopeOldestFirst(Builder $query): Builder
    {
        return $query->orderByRaw("CAST(SUBSTRING(equipment_id, 4) AS UNSIGNED) ASC");
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            0 => 'Available',
            1 => 'Assigned',
            2 => 'Under Maintenance',
            3 => 'Damaged',
            4 => 'Lost',
            5 => 'Disposed',
            default => 'Unknown',
        };
    }

    public function creator()
    {
        return $this->belongsTo(SuperAdmin::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function owningCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function scopeForActor($query, ?\Illuminate\Database\Eloquent\Model $actor)
    {
        if (!$actor) {
            return $query;
        }

        if ($actor instanceof SuperAdmin) {
            $companyId = $actor->company_id;
            return $query->where(function ($q) use ($actor, $companyId) {
                if ($companyId) {
                    $q->where('company_id', $companyId)
                      ->orWhere('created_by', $actor->id);
                } else {
                    $q->where('created_by', $actor->id);
                }
            });
        }

        if ($actor instanceof Member) {
            $companyId = $actor->company_id;
            return $query->where(function ($q) use ($actor, $companyId) {
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
                if ($actor->created_by) {
                    $q->orWhere('created_by', $actor->created_by);
                }
            });
        }

        return $query;
    }
}
