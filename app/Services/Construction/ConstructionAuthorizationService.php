<?php

namespace App\Services\Construction;

use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    public function isProjectOwnerOrSuperAdminEmulating(?Model $actor = null): bool
    {
        // 1. Direct project owner or super admin check on actor model
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        if ($actor instanceof Member) {
            if ((int) $actor->id === 1 || (string) $actor->id === '1' || (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1)) {
                return true;
            }
        }

        // 2. Check if a Super Admin / Project Owner is emulating any account
        if (session()->has('impersonator')) {
            $impersonator = session('impersonator');
            $guard = $impersonator['guard'] ?? null;
            $role = strtolower((string) ($impersonator['role'] ?? ''));

            if ($guard === 'superadmin' || str_contains($role, 'super admin') || str_contains($role, 'superadmin')) {
                return true;
            }

            if (!empty($impersonator['id'])) {
                $superAdmin = SuperAdmin::find($impersonator['id']);
                if ($superAdmin) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get all effective construction permissions for the actor.
     *
     * MemberRoleAssignment.status:
     * 1 = active
     * 0 = inactive
     *
     * @return array<int, string>
     */
    public function permissionsFor(
        ?Model $actor,
        ?int $projectId = null
    ): array {
        if (!$actor) {
            return [];
        }

        if ($this->isProjectOwnerOrSuperAdminEmulating($actor)) {
            return Permission::query()
                ->orderBy('slug')
                ->pluck('slug')
                ->all();
        }

        if ($actor instanceof Member && $actor->isAdmin()) {
            return Permission::query()
                ->orderBy('slug')
                ->pluck('slug')
                ->all();
        }

        $roleIds = [];
        $roleSlugs = [];

        // If an active role is set in session or request query, ensure it is considered
        $activeRoleSlug = session('active_role') ?? request()?->query('role');
        if ($activeRoleSlug) {
            $roleSlugs[] = strtolower(trim($activeRoleSlug));
        }

        if ($actor instanceof Member) {
            $assignmentRoleIds = DB::table('construction_member_role_assignments')
                ->where('member_id', $actor->getKey())
                ->where('status', 1)
                ->when($projectId !== null, function ($q) use ($projectId) {
                    $q->where(function ($sq) use ($projectId) {
                        $sq->where('project_id', $projectId)->orWhereNull('project_id');
                    });
                })
                ->pluck('role_id')
                ->toArray();
            $roleIds = array_merge($roleIds, $assignmentRoleIds);

            // Also check project team assignments
            $teamRoleIds = DB::table('construction_project_team_members')
                ->where('member_id', $actor->getKey())
                ->where('status', 'active')
                ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                ->pluck('role_id')
                ->toArray();
            $roleIds = array_merge($roleIds, $teamRoleIds);

            // Also check if assigned to drafting jobs
            $hasDraftingJob = DB::table('construction_drafting_jobs')
                ->where('assigned_to_member_id', $actor->getKey())
                ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                ->exists();
            if ($hasDraftingJob) {
                $roleSlugs[] = 'draft_person';
            }

            if (!empty($actor->assigned_roles)) {
                $roleSlugs = array_merge($roleSlugs, (array) $actor->assigned_roles);
            }

            if (!empty($actor->roles) && is_array($actor->roles)) {
                foreach ($actor->roles as $r) {
                    if (is_numeric($r)) {
                        $legacySlug = \App\Models\Role::where('id', $r)->value('slug');
                        if ($legacySlug) {
                            $roleSlugs[] = $legacySlug;
                        }
                    } elseif (is_string($r)) {
                        $roleSlugs[] = $r;
                    }
                }
            }
        }

        if (!empty($roleSlugs)) {
            $roleIdsFromSlugs = ConstructionRole::whereIn('slug', $roleSlugs)
                ->orWhereIn('name', $roleSlugs)
                ->pluck('id')
                ->toArray();
            $roleIds = array_merge($roleIds, $roleIdsFromSlugs);
        }

        $allRoleIds = array_values(array_unique(array_filter($roleIds)));

        if (empty($allRoleIds)) {
            if ($actor instanceof Member && ($actor->slug === 'admin' || $actor->slug === 'super-admin' || $actor->isAdmin())) {
                $adminRole = ConstructionRole::whereIn('slug', ['admin', 'project_admin', 'super_admin'])->first();
                if ($adminRole) {
                    $allRoleIds = [$adminRole->id];
                }
            }
        }

        $permissions = [];
        if (!empty($allRoleIds)) {
            $permissions = Permission::query()
                ->join('construction_role_permissions', 'construction_role_permissions.permission_id', '=', 'construction_permissions.id')
                ->whereIn('construction_role_permissions.role_id', $allRoleIds)
                ->distinct()
                ->orderBy('construction_permissions.slug')
                ->pluck('construction_permissions.slug')
                ->all();
        }
        return $permissions;
    }

    /**
     * Check whether the actor has at least one requested permission.
     *
     * @param array<int, string> $permissions
     */
    public function hasAnyPermission(
        ?Model $actor,
        array $permissions,
        ?int $projectId = null
    ): bool {
        if ($this->isProjectOwnerOrSuperAdminEmulating($actor)) {
            return true;
        }

        if (!$actor || empty($permissions)) {
            return false;
        }

        $userPerms = $this->permissionsFor($actor, $projectId);

        if (empty($userPerms)) {
            return false;
        }

        if (in_array('*', $userPerms, true)) {
            return true;
        }

        $permissionAliases = [
            'drawing_approval.manage' => ['drafting.manage', 'drafting.view', 'document.manage', 'drawing_approval.view'],
            'drafting.manage' => ['drawing_approval.manage', 'drawing_approval.view', 'document.manage', 'drafting.view'],
            'survey_plan.manage' => ['survey.manage', 'survey_submission.review', 'survey_plan.view', 'survey.view'],
            'survey.manage' => ['survey_plan.manage', 'survey.view', 'survey_plan.view'],
            'vehicle_tracking.manage' => ['vehicle.manage', 'vehicle_assignment.manage', 'vehicle_tracking.view'],
            'vehicle.manage' => ['vehicle_tracking.manage', 'vehicle.view'],
            'equipment_category.manage' => ['equipment.manage', 'equipment_category.view'],
            'equipment.manage' => ['equipment_category.manage', 'equipment_usage.manage', 'equipment_allocation.manage', 'equipment_tracking.manage', 'equipment_tracking.view', 'equipment.view'],
            'equipment_tracking.manage' => ['equipment.manage', 'equipment_allocation.manage', 'equipment_usage.manage', 'equipment_tracking.view'],
            'billing.manage' => ['billing_invoice.manage', 'billing_payment.manage', 'billing.view'],
            'billing_invoice.manage' => ['billing.manage', 'billing.view'],
            'billing_payment.manage' => ['billing.manage', 'billing.view'],
            'handover.manage' => ['project_closure.manage', 'handover.view'],
            'project_closure.manage' => ['handover.manage', 'handover.view'],
            'execution.manage' => ['execution_task.manage', 'execution.view', 'execution.task.view'],
            'execution_task.manage' => ['execution.manage', 'execution.task.view', 'execution.view'],
        ];

        foreach ($permissions as $reqPerm) {
            if (in_array($reqPerm, $userPerms, true)) {
                return true;
            }

            // Manage grants view/create/edit/delete
            if (str_ends_with($reqPerm, '.view')) {
                $base = substr($reqPerm, 0, -5);
                if (in_array("{$base}.manage", $userPerms, true)) {
                    return true;
                }
            }

            // Check aliases
            $aliases = $permissionAliases[$reqPerm] ?? [];
            foreach ($aliases as $alias) {
                if (in_array($alias, $userPerms, true)) {
                    return true;
                }
                if (str_ends_with($alias, '.view')) {
                    $base = substr($alias, 0, -5);
                    if (in_array("{$base}.manage", $userPerms, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function can(
        Member $member,
        string $permission,
        ?Project $project = null
    ): bool {
        return $this->hasAnyPermission(
            $member,
            [$permission],
            $project?->getKey()
        );
    }

    /**
     * Get active roles for the member.
     *
     * MemberRoleAssignment.status:
     * 1 = active
     * 0 = inactive
     */
    public function getRoles(
        Member $member,
        ?int $projectId = null
    ): Collection {
        $query = ConstructionRole::query()
            ->join(
                'construction_member_role_assignments',
                'construction_member_role_assignments.role_id',
                '=',
                'construction_roles.id'
            )
            ->where(
                'construction_member_role_assignments.member_id',
                $member->getKey()
            )
            ->where(
                'construction_member_role_assignments.status',
                1
            )
            ->where(
                'construction_roles.status',
                'active'
            )
            ->whereNull('construction_roles.deleted_at')
            ->distinct();

        $this->applyProjectScope($query, $projectId);

        return $query->get(['construction_roles.*']);
    }

    /**
     * Get active global roles (project_id IS NULL).
     *
     * MemberRoleAssignment.status:
     * 1 = active
     * 0 = inactive
     */
   public function getGlobalRoles(Member $member): Collection
{
    return ConstructionRole::query()
        ->whereHas('assignments', function ($query) use ($member) {
            $query
                ->where('member_id', $member->getKey())
                ->where('status', 1)
                ->whereNull('project_id');
        })
        ->where('status', 'active')
        ->whereNull('deleted_at')
        ->get();
}
    /**
     * Get projects accessible through active role assignments
     * or active project-team membership.
     */
    public function getProjects(Member $member): Collection
    {
        if ($this->isProjectOwnerOrSuperAdminEmulating($member)) {
            return Project::latest()->get();
        }

        $companyId = null;
        if ($member->created_by) {
            $creator = SuperAdmin::find($member->created_by);
            if ($creator && $creator->company_id) {
                $companyId = $creator->company_id;
            }
        }

        if ($member->isAdmin()) {
            if ($companyId) {
                return Project::where('company_id', $companyId)->latest()->get();
            }
            return Project::all();
        }

        $projectIds = MemberRoleAssignment::query()
            ->where(
                'member_id',
                $member->getKey()
            )
            ->where(
                'status',
                1
            )
            ->whereNotNull('project_id')
            ->pluck('project_id')
            ->merge(
                ProjectTeamMember::query()
                    ->where(
                        'member_id',
                        $member->getKey()
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->pluck('project_id')
            )
            ->unique()
            ->values();

        if ($projectIds->isNotEmpty()) {
            return Project::query()
                ->whereIn('id', $projectIds)
                ->latest()
                ->get();
        }

        return new Collection();
    }

    /**
     * @return array<int, string>
     */
    public function getPermissions(
        Member $member,
        ?int $projectId = null
    ): array {
        return $this->permissionsFor(
            $member,
            $projectId
        );
    }

    /**
     * Get permissions for one specific active member role.
     *
     * MemberRoleAssignment.status:
     * 1 = active
     * 0 = inactive
     *
     * @return array<int, string>
     */
    public function getPermissionsForRole(
        Member $member,
        ConstructionRole $role,
        ?int $projectId = null
    ): array {
        if ($this->isProjectOwnerOrSuperAdminEmulating($member)) {
            return Permission::query()
                ->orderBy('slug')
                ->pluck('slug')
                ->all();
        }

        $query = Permission::query()
            ->select('construction_permissions.slug')
            ->join(
                'construction_role_permissions',
                'construction_role_permissions.permission_id',
                '=',
                'construction_permissions.id'
            )
            ->join(
                'construction_roles',
                'construction_roles.id',
                '=',
                'construction_role_permissions.role_id'
            )
            ->join(
                'construction_member_role_assignments',
                'construction_member_role_assignments.role_id',
                '=',
                'construction_roles.id'
            )
            ->where(
                'construction_member_role_assignments.member_id',
                $member->getKey()
            )
            ->where(
                'construction_member_role_assignments.role_id',
                $role->getKey()
            )
            ->where(
                'construction_member_role_assignments.status',
                1
            )
            ->where(
                'construction_roles.status',
                'active'
            )
            ->whereNull('construction_roles.deleted_at')
            ->distinct();

        $this->applyProjectScope($query, $projectId);

        return $query
            ->orderBy('construction_permissions.slug')
            ->pluck('construction_permissions.slug')
            ->all();
    }

    public function resolveActiveRole(
        Member $member,
        ?string $requestedRole,
        ?int $projectId = null
    ): ?ConstructionRole {
        $roles = $this->getRoles(
            $member,
            $projectId
        );

        if ($requestedRole === null) {
            return $roles->count() === 1
                ? $roles->first()
                : null;
        }

        return $roles
            ->where('slug', $requestedRole)
            ->first();
    }

    /**
     * Project-scoped checks include both project-specific role assignments
     * and global company role assignments (project_id IS NULL).
     */
    private function applyProjectScope(
        Builder $query,
        ?int $projectId
    ): void {
        if ($projectId === null) {
            return;
        }

        $query->where(function (Builder $subQuery) use ($projectId) {
            $subQuery->where('construction_member_role_assignments.project_id', $projectId)
                     ->orWhereNull('construction_member_role_assignments.project_id');
        });
    }

    public function inferProjectId(Request $request): ?int
    {
        $projectId = $request->integer('project_id');

        if ($projectId > 0) {
            return $projectId;
        }

        foreach (
            $request->route()?->parameters() ?? []
            as $parameter
        ) {
            if ($parameter instanceof Model) {
                if (
                    $parameter->getTable()
                    === 'construction_projects'
                ) {
                    return (int) $parameter->getKey();
                }

                $relatedProjectId =
                    $parameter->getAttribute('project_id');

                if ($relatedProjectId !== null) {
                    return (int) $relatedProjectId;
                }
            }
        }

        return null;
    }
}