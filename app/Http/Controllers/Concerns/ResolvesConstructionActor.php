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

    protected function isCompanyAdminActor(?Model $actor = null): bool
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return false;
        }

        if (Auth::guard('admin')->check()) {
            return true;
        }

        if ($actor instanceof \App\Models\Member) {
            return $actor->isAdmin();
        }

        return false;
    }

    protected function isFullAdminActor(?Model $actor = null): bool
    {
        if (session()->has('impersonator')) {
            $impersonator = session('impersonator');
            $guard = $impersonator['guard'] ?? null;
            $role = strtolower((string) ($impersonator['role'] ?? ''));
            if ($guard === 'superadmin' || str_contains($role, 'super admin') || str_contains($role, 'superadmin')) {
                return true;
            }
        }

        $actor = $actor ?? $this->constructionActor();

        if ($actor instanceof \App\Models\SuperAdmin || Auth::guard('superadmin')->check()) {
            return true;
        }

        if (Auth::guard('admin')->check()) {
            return true;
        }

        if ($actor instanceof \App\Models\Member && $actor->isAdmin()) {
            return true;
        }

        return false;
    }

    protected function isProjectOwner(?Model $actor = null): bool
    {
        if (session()->has('impersonator')) {
            $impersonator = session('impersonator');
            $guard = $impersonator['guard'] ?? null;
            $role = strtolower((string) ($impersonator['role'] ?? ''));
            if ($guard === 'superadmin' || str_contains($role, 'super admin') || str_contains($role, 'superadmin')) {
                return true;
            }
        }

        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return false;
        }

        if ($actor instanceof \App\Models\SuperAdmin) {
            return empty($actor->company_id) && ((int) $actor->id === 1 || (string) $actor->id === '1' || (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1));
        }

        if ($actor instanceof \App\Models\Member) {
            return empty($actor->company_id) && ((int) $actor->id === 1 || (string) $actor->id === '1' || (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1));
        }

        return false;
    }

    protected function getAccessibleProjectIds(?Model $actor = null): \Illuminate\Support\Collection
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return collect();
        }

        // 1. Platform Super Admin (SuperAdmin id == 1, is_project_owner == 1, or without company_id): always sees ALL projects
        if ($this->isProjectOwner($actor)) {
            return \App\Models\Project::pluck('id');
        }

        // 2. Company Owner / Company SuperAdmin (SuperAdmin with company_id): always sees ALL projects of that company
        if ($actor instanceof \App\Models\SuperAdmin) {
            $companyId = $actor->company_id;
            $query = \App\Models\Project::query();
            if ($companyId) {
                $query->where(function ($q) use ($companyId, $actor) {
                    $q->where('company_id', $companyId)
                      ->orWhere(function ($sq) use ($actor) {
                          $sq->where('created_by_type', \App\Models\SuperAdmin::class)
                             ->where('created_by_id', $actor->id);
                      });
                });
            } else {
                $query->where('created_by_type', \App\Models\SuperAdmin::class)
                      ->where('created_by_id', $actor->id);
            }
            return $query->pluck('id');
        }

        // 3. Company Admin (Admin guard or Member with Admin role): always sees ALL projects of that company
        if ($this->isCompanyAdminActor($actor)) {
            $companyId = $this->getAccessibleCompanyId($actor);
            $query = \App\Models\Project::query();
            if ($companyId) {
                $query->where('company_id', $companyId);
            } else {
                $query->where('created_by_type', $actor::class)
                      ->where('created_by_id', $actor->getKey());
            }
            return $query->pluck('id');
        }

        // 4. Regular Member / Employee: ONLY sees projects they are explicitly assigned to
        $assignedProjectIds = \App\Models\ProjectTeamMember::where('member_id', $actor->getKey())
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'inactive');
            })
            ->pluck('project_id')
            ->merge(
                \App\Models\MemberRoleAssignment::where('member_id', $actor->getKey())
                    ->where('status', 1)
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->unique()
            ->filter()
            ->values();

        if ($assignedProjectIds->isEmpty()) {
            return collect();
        }

        return \App\Models\Project::whereIn('id', $assignedProjectIds)->pluck('id');
    }

    protected function getAccessibleCompanyId(?Model $actor = null): ?int
    {
        $actor = $actor ?? $this->constructionActor();

        if (!$actor) {
            return null;
        }

        if ($actor instanceof \App\Models\SuperAdmin) {
            return $actor->company_id ? (int) $actor->company_id : null;
        }

        if ($actor instanceof \App\Models\Member) {
            if (!empty($actor->company_id)) {
                return (int) $actor->company_id;
            }
            if ($actor->created_by) {
                $creator = \App\Models\SuperAdmin::find($actor->created_by);
                if ($creator && $creator->company_id) {
                    return (int) $creator->company_id;
                }
            }
            if (!empty($actor->company_name)) {
                $company = \App\Models\Company::where('name', $actor->company_name)
                    ->orWhere('legal_name', $actor->company_name)
                    ->first();
                if ($company) {
                    return (int) $company->id;
                }
            }
        }

        return null;
    }
}
