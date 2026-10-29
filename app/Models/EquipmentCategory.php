<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by',
        'company_id',
        'category_id',
        'category_name',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (EquipmentCategory $category) {
            if (empty($category->category_id)) {
                $category->category_id = static::generateCategoryId();
            }
        });
    }

    public static function generateCategoryId(): string
    {
        $prefix = 'EC-';
        do {
            $random = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $categoryId = $prefix . $random;
        } while (static::where('category_id', $categoryId)->exists());

        return $categoryId;
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class, 'category_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            1 => 'Active',
            0 => 'Inactive',
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

    public function scopeForActor($query, ?\Illuminate\Database\Eloquent\Model $actor)
    {
        if (!$actor) {
            return $query;
        }

        if ($actor instanceof SuperAdmin) {
            $isProjectOwner = (int) $actor->id === 1
                || (string) $actor->id === '1'
                || (!empty($actor->is_project_owner));

            if ($isProjectOwner) {
                // Project Owner sees all equipment categories from every owner/company
                return $query;
            }

            $companyId = $actor->company_id;
            return $query->where(function ($q) use ($actor, $companyId) {
                if ($companyId) {
                    $q->where('company_id', $companyId)
                      ->orWhere('created_by', $actor->id)
                      ->orWhereNull('company_id');
                } else {
                    $q->where('created_by', $actor->id)
                      ->orWhereNull('company_id');
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
                $q->orWhereNull('company_id');
            });
        }

        return $query;
    }
}
