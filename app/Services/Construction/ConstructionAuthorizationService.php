<?php

namespace App\Services\Construction;

use App\Models\Construction\Permission;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConstructionAuthorizationService
{
    public function resolveActor(?Request $request = null): ?Model
    {
        foreach (['superadmin', 'admin', 'member', 'callingteam'] as $guard) {
            if (Auth::guard($guard)->check()) {
                /** @var Model $user */
                $user = Auth::guard($guard)->user();

                return $user;
            }
        }

        $requestUser = $request?->user();

        return $requestUser instanceof Model ? $requestUser : null;
    }

    /**
     * @return array<int, string>
     */
    public function permissionsFor(?Model $actor, ?int $projectId = null): array
    {
        if (!$actor) {
            return [];
        }

        if ($actor instanceof SuperAdmin) {
            return Permission::query()
                ->orderBy('slug')
                ->pluck('slug')
                ->all();
        }

        if (!$actor instanceof Member) {
            return [];
        }

        $query = Permission::query()
            ->select('construction_permissions.slug')
            ->join('construction_role_permissions', 'construction_role_permissions.permission_id', '=', 'construction_permissions.id')
            ->join('construction_roles', 'construction_roles.id', '=', 'construction_role_permissions.role_id')
            ->join('construction_member_role_assignments', 'construction_member_role_assignments.role_id', '=', 'construction_roles.id')
            ->where('construction_member_role_assignments.member_id', $actor->getKey())
            ->where('construction_roles.status', 'active')
            ->whereNull('construction_roles.deleted_at')
            ->distinct();

        if ($projectId !== null) {
            $query->where('construction_member_role_assignments.project_id', $projectId);
        }

        return $query
            ->orderBy('construction_permissions.slug')
            ->pluck('construction_permissions.slug')
            ->all();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function hasAnyPermission(?Model $actor, array $permissions, ?int $projectId = null): bool
    {
        if (!$actor || $permissions === []) {
            return false;
        }

        if ($actor instanceof SuperAdmin) {
            return true;
        }

        if (!$actor instanceof Member) {
            return false;
        }

        return Permission::query()
            ->join('construction_role_permissions', 'construction_role_permissions.permission_id', '=', 'construction_permissions.id')
            ->join('construction_roles', 'construction_roles.id', '=', 'construction_role_permissions.role_id')
            ->join('construction_member_role_assignments', 'construction_member_role_assignments.role_id', '=', 'construction_roles.id')
            ->where('construction_member_role_assignments.member_id', $actor->getKey())
            ->where('construction_roles.status', 'active')
            ->whereNull('construction_roles.deleted_at')
            ->whereIn('construction_permissions.slug', $permissions)
            ->when($projectId !== null, function ($query) use ($projectId) {
                $query->where('construction_member_role_assignments.project_id', $projectId);
            })
            ->exists();
    }

    public function inferProjectId(Request $request): ?int
    {
        $projectId = $request->integer('project_id');

        if ($projectId > 0) {
            return $projectId;
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                if ($parameter->getTable() === 'construction_projects') {
                    return (int) $parameter->getKey();
                }

                $relatedProjectId = $parameter->getAttribute('project_id');

                if ($relatedProjectId !== null) {
                    return (int) $relatedProjectId;
                }
            }
        }

        return null;
    }
}
