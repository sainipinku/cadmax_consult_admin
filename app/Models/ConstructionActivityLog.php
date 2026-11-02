<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ConstructionActivityLog extends Model
{
    public $timestamps = false;

    protected $table = 'construction_activity_logs';

    protected $fillable = [
        'company_id',
        'project_id',
        'actor_type',
        'actor_id',
        'employee_id',
        'channel',
        'module',
        'action',
        'reference_type',
        'reference_id',
        'reference_code',
        'description',
        'meta',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope query to the viewing actor's authorized visibility boundary.
     */
    public function scopeForActor(Builder $query, ?Model $actor): Builder
    {
        if (!$actor) {
            return $query->whereRaw('1 = 0');
        }

        // 1. Root Project Owner (SuperAdmin #1 or is_project_owner or without company_id)
        if ($actor instanceof SuperAdmin && ((int)$actor->id === 1 || !empty($actor->is_project_owner) || empty($actor->company_id))) {
            return $query;
        }

        // 2. Company Super Admin
        if ($actor instanceof SuperAdmin && $actor->company_id) {
            $companyId = (int)$actor->company_id;

            $companyMemberIds = Member::where(function ($sq) use ($companyId, $actor) {
                $companySuperAdminIds = SuperAdmin::where('company_id', $companyId)->pluck('id')->toArray();
                if (!empty($companySuperAdminIds)) {
                    $sq->whereIn('created_by', $companySuperAdminIds);
                }
                $sq->orWhere('created_by', $actor->id);
                if ($actor->company) {
                    $sq->orWhere('company_name', $actor->company->name);
                }
            })->pluck('id')->toArray();

            $companyProjectIds = Project::where('company_id', $companyId)->pluck('id')->toArray();

            return $query->where(function (Builder $sq) use ($companyId, $companyProjectIds, $companyMemberIds) {
                $sq->where('company_id', $companyId);
                if (!empty($companyProjectIds)) {
                    $sq->orWhereIn('project_id', $companyProjectIds);
                }
                if (!empty($companyMemberIds)) {
                    $sq->orWhere(function ($mQuery) use ($companyMemberIds) {
                        $mQuery->where('actor_type', Member::class)
                               ->whereIn('actor_id', $companyMemberIds);
                    });
                }
            });
        }

        // 3. Project Admin / Member
        if ($actor instanceof Member) {
            $accessibleProjectIds = ProjectTeamMember::where('member_id', $actor->id)
                ->where('status', 'active')
                ->pluck('project_id')
                ->toArray();

            return $query->where(function (Builder $sq) use ($accessibleProjectIds, $actor) {
                if (!empty($accessibleProjectIds)) {
                    $sq->whereIn('project_id', $accessibleProjectIds);
                }
                $sq->orWhere(function ($selfQ) use ($actor) {
                    $selfQ->where('actor_type', Member::class)
                          ->where('actor_id', $actor->id);
                });
            });
        }

        return $query->whereRaw('1 = 0');
    }
}

