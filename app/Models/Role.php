<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

class Role extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'status',
        'created_by',
        'super_admin_id',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'status' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($role) {
            if (empty($role->created_by)) {
                $role->created_by = auth('superadmin')->id() ?? auth()->id() ?? 1;
            }
        });
    }

    public function uniqueIds()
    {
        return ['uuid'];
    }

    public function creator()
    {
        return $this->belongsTo(SuperAdmin::class, 'created_by');
    }

    public function superAdmin()
    {
        return $this->belongsTo(SuperAdmin::class, 'super_admin_id');
    }

    public function isDefaultRole(): bool
    {
        return (bool) $this->is_default
            || in_array($this->slug, [
                'member',
                'super_admin',
                'superadmin',
                'project_admin',
                'surveyor',
                'draft_person',
                'vehicle_driver',
                'review_approver',
                'site_employee',
                'admin',
            ], true);
    }

    /**
     * Scope roles accessible to a given user (SuperAdmin or Member/Admin).
     * Default roles show to everyone. Custom roles only show to their SuperAdmin/Admin.
     */
    public function scopeAccessibleFor($query, mixed $user)
    {
        if (!$user) {
            return $query->where(function ($q) {
                $q->where('is_default', 1)->orWhereNull('super_admin_id');
            });
        }

        $isProjectOwner = ((int) $user->id === 1 || (string) $user->id === '1' || (isset($user->is_project_owner) && (int) $user->is_project_owner === 1));

        if ($isProjectOwner) {
            return $query;
        }

        $superAdminId = null;
        if ($user instanceof SuperAdmin) {
            $superAdminId = (int) $user->id;
        } elseif ($user instanceof Member) {
            $superAdminId = (int) ($user->created_by ?: $user->assigned_admin_id);
        }

        return $query->where(function ($q) use ($superAdminId, $user) {
            $q->where('is_default', 1)
                ->orWhereNull('super_admin_id')
                ->orWhere('created_by', 1);

            if ($superAdminId) {
                $q->orWhere('super_admin_id', $superAdminId)
                    ->orWhere('created_by', $superAdminId);
            }

            if ($user && isset($user->id)) {
                $q->orWhere('created_by', $user->id);
            }
        });
    }
}

