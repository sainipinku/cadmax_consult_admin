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

    public function isProjectOwner(?Model $actor = null): bool
    {
        $actor = $actor ?? $this->resolveActor();

        if (!$actor) {
            return false;
        }

        if (session()->has('impersonator')) {
            if ($actor instanceof SuperAdmin) {
                return (int) $actor->id === 1 || (string) $actor->id === '1' || (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1);
            }
            if ($actor instanceof Member) {
                return isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1;
            }
            return false;
        }

        if ($actor instanceof SuperAdmin) {
            $activeRoleSlug = session('active_role') ?? request()?->query('role');
            if ($activeRoleSlug && !in_array($activeRoleSlug, ['super_admin', 'superadmin'])) {
                return false;
            }

            return (int) $actor->id === 1 || (string) $actor->id === '1' || (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1) || empty($actor->company_id);
        }

        if ($actor instanceof Member) {
            $activeRoleSlug = session('active_role') ?? request()?->query('role');
            if ($activeRoleSlug && !in_array($activeRoleSlug, ['super_admin', 'superadmin', 'admin', 'project_admin'])) {
                return false;
            }

            return isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1;
        }

        return false;
    }

    public function isProjectOwnerOrSuperAdminEmulating(?Model $actor = null): bool
    {
        // 1. Check if an impersonator is present
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

        // 2. Direct project owner check on actor model
        if ($actor instanceof SuperAdmin) {
            $activeRoleSlug = session('active_role') ?? request()?->query('role');
            if ($activeRoleSlug && !in_array($activeRoleSlug, ['super_admin', 'superadmin'])) {
                return false;
            }

            return (int) $actor->id === 1 || (string) $actor->id === '1' || (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1) || empty($actor->company_id);
        }

        if ($actor instanceof Member) {
            $activeRoleSlug = session('active_role') ?? request()?->query('role');
            if ($activeRoleSlug && !in_array($activeRoleSlug, ['super_admin', 'superadmin', 'admin', 'project_admin'])) {
                return false;
            }

            if (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1) {
                return true;
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

        $companyId = $this->resolveCompanyId($actor);

        // 1. SuperAdmin checks
        if ($actor instanceof SuperAdmin) {
            $isRoot = (int) $actor->id === 1 || (string) $actor->id === '1' || (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1) || empty($actor->company_id);

            $activeRoleSlug = session('active_role') ?? request()?->query('role');
            if ($activeRoleSlug) {
                $activeRoleSlug = strtolower(trim($activeRoleSlug));
            }

            $isRestrictedRole = $activeRoleSlug && !in_array($activeRoleSlug, ['super_admin', 'superadmin', 'crm_super_admin', 'admin', 'project_admin'], true);

            // Project owner without an active restricted worker role has full global permissions
            if ($isRoot && !$isRestrictedRole) {
                return Permission::query()
                    ->orderBy('slug')
                    ->pluck('slug')
                    ->all();
            }

            // Check if active role is set in session or request query
            if ($activeRoleSlug) {
                $activeCRole = null;
                if ($companyId) {
                    $activeCRole = ConstructionRole::where('company_id', $companyId)
                        ->where(fn($q) => $q->where('slug', $activeRoleSlug)->orWhere('name', $activeRoleSlug))
                        ->first();
                }
                if (!$activeCRole) {
                    $activeCRole = ConstructionRole::whereNull('company_id')
                        ->where(fn($q) => $q->where('slug', $activeRoleSlug)->orWhere('name', $activeRoleSlug))
                        ->first();
                }
                if (!$activeCRole) {
                    $activeCRole = ConstructionRole::where('slug', $activeRoleSlug)->orWhere('name', $activeRoleSlug)->first();
                }

                if ($activeCRole) {
                    return Permission::query()
                        ->join('construction_role_permissions', 'construction_role_permissions.permission_id', '=', 'construction_permissions.id')
                        ->where('construction_role_permissions.role_id', $activeCRole->id)
                        ->distinct()
                        ->orderBy('construction_permissions.slug')
                        ->pluck('construction_permissions.slug')
                        ->all();
                }
            }

            if ($isRoot) {
                return Permission::query()
                    ->orderBy('slug')
                    ->pluck('slug')
                    ->all();
            }

            // Otherwise, resolve role from actor->roles
            $roleSlugs = [];
            if (!empty($actor->roles) && is_array($actor->roles)) {
                foreach ($actor->roles as $r) {
                    if (is_string($r)) {
                        $roleSlugs[] = strtolower(trim($r));
                    }
                }
            }
            if (empty($roleSlugs)) {
                $roleSlugs = ['super_admin'];
            }

            if (!empty($roleSlugs)) {
                $roleIds = [];
                if ($companyId) {
                    $roleIds = ConstructionRole::where('company_id', $companyId)
                        ->where(fn($q) => $q->whereIn('slug', $roleSlugs)->orWhereIn('name', $roleSlugs))
                        ->pluck('id')
                        ->toArray();
                }
                if (empty($roleIds)) {
                    $roleIds = ConstructionRole::whereNull('company_id')
                        ->where(fn($q) => $q->whereIn('slug', $roleSlugs)->orWhereIn('name', $roleSlugs))
                        ->pluck('id')
                        ->toArray();
                }
                if (empty($roleIds)) {
                    $roleIds = ConstructionRole::whereIn('slug', $roleSlugs)
                        ->orWhereIn('name', $roleSlugs)
                        ->pluck('id')
                        ->toArray();
                }

                if (!empty($roleIds)) {
                    $perms = Permission::query()
                        ->join('construction_role_permissions', 'construction_role_permissions.permission_id', '=', 'construction_permissions.id')
                        ->whereIn('construction_role_permissions.role_id', $roleIds)
                        ->distinct()
                        ->orderBy('construction_permissions.slug')
                        ->pluck('construction_permissions.slug')
                        ->all();

                    if (!empty($perms)) {
                        return $perms;
                    }
                }

                if (in_array('super_admin', $roleSlugs, true) || in_array('superadmin', $roleSlugs, true)) {
                    return Permission::query()
                        ->orderBy('slug')
                        ->pluck('slug')
                        ->all();
                }
            }

            if ($isRoot) {
                return Permission::query()
                    ->orderBy('slug')
                    ->pluck('slug')
                    ->all();
            }

            return [];
        }

        // 3. Member checks
        if ($actor instanceof Member) {
            if (isset($actor->is_project_owner) && (int) $actor->is_project_owner === 1) {
                return Permission::query()
                    ->orderBy('slug')
                    ->pluck('slug')
                    ->all();
            }
        }

        $roleIds = [];
        $roleSlugs = [];

        // If an active role is set in session or request query, ensure it is considered
        if ($projectId === null) {
            $activeRoleSlug = session('active_role') ?? request()?->query('role');
            if ($activeRoleSlug) {
                $roleSlugs[] = strtolower(trim($activeRoleSlug));
            }
        }

        if ($actor instanceof Member) {
            $assignmentRoleIds = DB::table('construction_member_role_assignments')
                ->where('member_id', $actor->getKey())
                ->where('status', 1)
                ->when($projectId !== null, function ($q) use ($projectId) {
                    $q->where('project_id', $projectId);
                })
                ->pluck('role_id')
                ->toArray();
            $roleIds = array_merge($roleIds, $assignmentRoleIds);

            // Also check if assigned to drafting jobs
            $hasDraftingJob = DB::table('construction_drafting_jobs')
                ->where('assigned_to_member_id', $actor->getKey())
                ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                ->exists();
            if ($hasDraftingJob) {
                $roleSlugs[] = 'draft_person';
            }

            if ($projectId === null) {
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
        }

        if (!empty($roleSlugs)) {
            $roleIdsFromSlugs = [];
            if ($companyId) {
                $roleIdsFromSlugs = ConstructionRole::where('company_id', $companyId)
                    ->where(fn($q) => $q->whereIn('slug', $roleSlugs)->orWhereIn('name', $roleSlugs))
                    ->pluck('id')
                    ->toArray();
            }
            if (empty($roleIdsFromSlugs)) {
                $roleIdsFromSlugs = ConstructionRole::whereNull('company_id')
                    ->where(fn($q) => $q->whereIn('slug', $roleSlugs)->orWhereIn('name', $roleSlugs))
                    ->pluck('id')
                    ->toArray();
            }
            if (empty($roleIdsFromSlugs)) {
                $roleIdsFromSlugs = ConstructionRole::whereIn('slug', $roleSlugs)
                    ->orWhereIn('name', $roleSlugs)
                    ->pluck('id')
                    ->toArray();
            }
            $roleIds = array_merge($roleIds, $roleIdsFromSlugs);
        }

        $allRoleIds = array_values(array_unique(array_filter($roleIds)));

        if (!empty($allRoleIds)) {
            $allRoleIds = ConstructionRole::whereIn('id', $allRoleIds)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
        }

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
        if ($this->isProjectOwner($actor)) {
            return true;
        }

        if (!$actor || empty($permissions)) {
            return false;
        }

        $flatPermissions = [];
        foreach ($permissions as $p) {
            foreach (explode(',', (string) $p) as $sub) {
                $sub = trim($sub);
                if ($sub !== '') {
                    $flatPermissions[] = $sub;
                }
            }
        }
        $permissions = !empty($flatPermissions) ? $flatPermissions : $permissions;

        $userPerms = $this->permissionsFor($actor, $projectId);

        if (empty($userPerms)) {
            if ($actor instanceof SuperAdmin && (empty($actor->roles) || in_array('super_admin', (array) $actor->roles)) && Permission::count() === 0) {
                return true;
            }
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
            'equipment_allocation.manage' => ['equipment_tracking.manage', 'equipment.manage'],
            'equipment_usage.manage' => ['equipment_tracking.manage', 'equipment.manage'],
            'equipment_tracking.view' => ['equipment_tracking.manage', 'equipment.manage', 'equipment.view'],
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
        if ((int) $member->id === 1 || (string) $member->id === '1' || (isset($member->is_project_owner) && (int) $member->is_project_owner === 1)) {
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
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', '!=', 'inactive');
                    })
                    ->pluck('project_id')
            )
            ->merge(
                \App\Models\ExecutionTask::where('supervisor_member_id', $member->getKey())
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                DB::table('construction_execution_task_assignees')
                    ->where('member_id', $member->getKey())
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                DB::table('task_assignments')
                    ->where('assigned_to', $member->getKey())
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                \App\Models\Task::where(function ($q) use ($member) {
                    $q->where('member_id', $member->getKey())
                      ->orWhere('assigned_supervisor_member_id', $member->getKey());
                })
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                DB::table('construction_survey_plan_members')
                    ->join('construction_survey_plans', 'construction_survey_plans.id', '=', 'construction_survey_plan_members.survey_plan_id')
                    ->where('construction_survey_plan_members.member_id', $member->getKey())
                    ->whereNotNull('construction_survey_plans.project_id')
                    ->pluck('construction_survey_plans.project_id')
            )
            ->merge(
                DB::table('construction_drafting_jobs')
                    ->where('assigned_to_member_id', $member->getKey())
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                \App\Models\VehicleAssignment::where('driver_member_id', $member->getKey())
                    ->where('status', 'active')
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->merge(
                DB::table('construction_equipment_allocations')
                    ->where('assigned_to_member_id', $member->getKey())
                    ->where('status', 'active')
                    ->whereNotNull('project_id')
                    ->pluck('project_id')
            )
            ->unique()
            ->filter()
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
            as $paramKey => $parameter
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
            } elseif (is_numeric($parameter)) {
                if (in_array($paramKey, ['project', 'projectId', 'project_id'], true)) {
                    return (int) $parameter;
                }
                if (in_array($paramKey, ['surveyPlan', 'survey_plan'], true)) {
                    $pid = DB::table('construction_survey_plans')->where('id', $parameter)->value('project_id');
                    if ($pid) {
                        return (int) $pid;
                    }
                }
                if (in_array($paramKey, ['task', 'taskId', 'task_id'], true)) {
                    $pid = DB::table('tasks')->where('id', $parameter)->value('project_id')
                        ?? DB::table('construction_tasks')->where('id', $parameter)->value('project_id');
                    if ($pid) {
                        return (int) $pid;
                    }
                }
            }
        }

        return null;
    }

    public function resolveCompanyId(?Model $actor): ?int
    {
        if (!$actor) {
            return null;
        }

        if ($actor instanceof SuperAdmin) {
            return $actor->company_id ? (int) $actor->company_id : null;
        }

        if ($actor instanceof Member) {
            if (!empty($actor->company_id)) {
                return (int) $actor->company_id;
            }
            if ($actor->created_by) {
                $creator = SuperAdmin::find($actor->created_by);
                if ($creator && $creator->company_id) {
                    return (int) $creator->company_id;
                }
            }
            if (!empty($actor->company_name)) {
                $comp = \App\Models\Company::where('name', $actor->company_name)
                    ->orWhere('legal_name', $actor->company_name)
                    ->first();
                if ($comp) {
                    return (int) $comp->id;
                }
            }
        }

        return null;
    }
}