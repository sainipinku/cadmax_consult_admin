<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait ResolvesConstructionActor
{
    protected function constructionActor(): ?Model
    {
        foreach (['superadmin', 'admin', 'member', 'callingteam'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return Auth::guard($guard)->user();
            }
        }

        return Auth::user();
    }

    protected function isFullAdminActor(?Model $actor = null): bool
    {
        $actor = $actor ?? $this->constructionActor();

        if ($actor instanceof \App\Models\SuperAdmin || Auth::guard('superadmin')->check()) {
            return true;
        }

        if (Auth::guard('admin')->check()) {
            return true;
        }

        if ($actor && method_exists($actor, 'isAdmin') && $actor->isAdmin()) {
            return true;
        }

        return false;
    }

    protected function getAccessibleProjectIds(?Model $actor = null): \Illuminate\Support\Collection
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return collect();
        }

        // 1. Check if Project Owner (SuperAdmin id == 1 or is_project_owner == 1)
        $isProjectOwner = false;
        if ($actor instanceof \App\Models\SuperAdmin) {
            if ((int) $actor->id === 1 || (string) $actor->id === '1' || (isset($actor->is_project_owner) && (int)$actor->is_project_owner === 1)) {
                $isProjectOwner = true;
            }
        }

        if ($isProjectOwner) {
            return \App\Models\Project::pluck('id');
        }

        // 2. Resolve Company ID for actor
        $companyId = null;
        if ($actor instanceof \App\Models\SuperAdmin) {
            $companyId = $actor->company_id;
        } elseif ($actor instanceof \App\Models\Member) {
            if ($actor->created_by) {
                $creator = \App\Models\SuperAdmin::find($actor->created_by);
                if ($creator) {
                    $companyId = $creator->company_id;
                }
            }
        }

        // 3. Assigned project IDs from ProjectTeamMember
        $assignedProjectIds = \App\Models\ProjectTeamMember::where('member_id', $actor->getKey())->pluck('project_id');

        $query = \App\Models\Project::query();

        if ($companyId) {
            $query->where(function ($q) use ($companyId, $assignedProjectIds, $actor) {
                $q->where('company_id', $companyId);

                if ($assignedProjectIds->isNotEmpty()) {
                    $q->orWhereIn('id', $assignedProjectIds);
                }

                if ($actor instanceof \App\Models\SuperAdmin) {
                    $q->orWhere(function ($sq) use ($actor) {
                        $sq->where('created_by_type', \App\Models\SuperAdmin::class)
                           ->where('created_by_id', $actor->id);
                    });
                }
            });
        } elseif ($assignedProjectIds->isNotEmpty()) {
            $query->whereIn('id', $assignedProjectIds);
        } else {
            if ($actor instanceof \App\Models\SuperAdmin) {
                $query->where('created_by_type', \App\Models\SuperAdmin::class)
                      ->where('created_by_id', $actor->id);
            } elseif ($actor instanceof \App\Models\Member) {
                $query->where('created_by_type', \App\Models\Member::class)
                      ->where('created_by_id', $actor->id);
            }
        }

        return $query->pluck('id');
    }

    protected function getAccessibleCompanyId(?Model $actor = null): ?int
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return null;
        }

        if ($actor instanceof \App\Models\SuperAdmin) {
            if ((int) $actor->id === 1 || (string) $actor->id === '1' || (isset($actor->is_project_owner) && (int)$actor->is_project_owner === 1)) {
                return null;
            }
            return $actor->company_id ? (int) $actor->company_id : null;
        }

        if ($actor instanceof \App\Models\Member) {
            if ($actor->created_by) {
                $creator = \App\Models\SuperAdmin::find($actor->created_by);
                if ($creator && $creator->company_id) {
                    return (int) $creator->company_id;
                }
            }
        }

        return null;
    }
}
