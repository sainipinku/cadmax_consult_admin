<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Models\Role;
use App\Models\ConstructionRole;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class RolesController extends Controller
{
    /**
     * Get paginated roles with search, status, and permissions.
     * Default roles are visible to everyone. Custom roles are only visible to their Super Admin/Admin.
     */
    public function list(Request $request)
    {
        $authSvc = app(\App\Services\Construction\ConstructionAuthorizationService::class);
        $currentUser = Auth::guard('superadmin')->user()
            ?? Auth::guard('admin')->user()
            ?? Auth::guard('member')->user()
            ?? Auth::user();

        if (!$currentUser instanceof \App\Models\SuperAdmin
            && !$authSvc->hasAnyPermission($currentUser, ['role.view', 'role.manage', 'role.create', 'role.edit', 'role.delete', 'role.assign_permissions'])
        ) {
            abort(403);
        }

        // Ensure global system ConstructionRole templates exist in Role table as default roles
        $constructionRoles = ConstructionRole::whereNull('company_id')->get();
        foreach ($constructionRoles as $cRole) {
            Role::firstOrCreate(
                ['slug' => $cRole->slug],
                [
                    'name' => $cRole->name,
                    'status' => $cRole->status === 'active' ? 1 : 0,
                    'is_default' => 1,
                    'super_admin_id' => null,
                    'created_by' => 1,
                ]
            );
        }

        $rolesQuery = Role::with(['creator', 'superAdmin'])
            ->accessibleFor($currentUser)
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->status, function ($q) use ($request) {
                $statusValue = $request->status == 'active' ? 1 : 0;
                $q->where('status', $statusValue);
            })
            ->when($request->created_by, fn($q) => $q->where('created_by', $request->created_by))
            ->latest('id');

        $roles = $rolesQuery->paginate($request->per_page ?? 10);

        $companyId = $this->resolveCompanyId($currentUser);
        if (!$companyId && $request->filled('company_id')) {
            $companyId = (int) $request->input('company_id');
        }

        // Map assigned permission IDs & slugs to each role
        $roles->getCollection()->transform(function ($role) use ($companyId) {
            $role->is_default = $role->isDefaultRole();
            $cRole = null;
            if ($companyId) {
                $cRole = ConstructionRole::where('company_id', $companyId)->where('slug', $role->slug)->first();
            }
            if (!$cRole) {
                $cRole = ConstructionRole::whereNull('company_id')->where('slug', $role->slug)->first();
            }
            if (!$cRole) {
                $cRole = ConstructionRole::where('slug', $role->slug)->first();
            }

            if ($cRole) {
                $role->construction_role_id = $cRole->id;
                $role->permissions = DB::table('construction_role_permissions')
                    ->join('construction_permissions', 'construction_permissions.id', '=', 'construction_role_permissions.permission_id')
                    ->where('construction_role_permissions.role_id', $cRole->id)
                    ->pluck('construction_permissions.id')
                    ->toArray();
                $role->permission_slugs = DB::table('construction_role_permissions')
                    ->join('construction_permissions', 'construction_permissions.id', '=', 'construction_role_permissions.permission_id')
                    ->where('construction_role_permissions.role_id', $cRole->id)
                    ->pluck('construction_permissions.slug')
                    ->toArray();
            } else {
                $role->construction_role_id = null;
                $role->permissions = [];
                $role->permission_slugs = [];
            }
            return $role;
        });

        // Group permissions by module for the permission modal
        $allPermissions = Permission::orderBy('module')->orderBy('name')->get();
        $groupedPermissions = $allPermissions->groupBy('module');

        return Inertia::render('SuperAdmin/Roles/List', [
            'roles' => $roles,
            'all_permissions' => $allPermissions,
            'grouped_permissions' => $groupedPermissions,
            'filters' => $request->only(['search', 'status', 'created_by', 'per_page'])
        ]);
    }

    /**
     * Create or update a role.
     * Default roles cannot be edited.
     */
    public function addRole(Request $request, $uuid = null)
    {
        $authSvc = app(\App\Services\Construction\ConstructionAuthorizationService::class);
        $actor = $authSvc->resolveActor($request);
        $currentUser = Auth::guard('superadmin')->user()
            ?? Auth::guard('admin')->user()
            ?? Auth::guard('member')->user()
            ?? $actor;

        $requiredPermission = $uuid ? 'role.edit' : 'role.create';
        if (!$authSvc->hasAnyPermission($actor, [$requiredPermission, 'role.manage'])) {
            if (!$currentUser instanceof \App\Models\SuperAdmin) {
                abort(403);
            }
            return redirect()->back()->with('error', 'Unauthorized: You do not have permission to modify roles.');
        }

        $companyId = $this->resolveCompanyId($currentUser);

        if ($uuid) {
            $existingRole = Role::where('uuid', $uuid)->firstOrFail();
            if (!$this->roleBelongsToCompany($existingRole, $companyId)) {
                abort(403);
            }
            if ($existingRole->isDefaultRole()) {
                return redirect()->back()->with('error', 'Default system roles cannot be edited.');
            }
        }

        $name = rtrim($request->input('name'), " .");
        $slug = $this->generateUniqueSlug($name, $uuid);

        $validationRules = [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($uuid) {
                    $cleanedName = rtrim($value, " .");
                    $existing = Role::whereRaw('LOWER(name) = ?', [strtolower($cleanedName)])
                        ->whereNull('deleted_at')
                        ->when($uuid, fn($q) => $q->where('uuid', '!=', $uuid))
                        ->first();

                    if ($existing) {
                        $fail('The role name already exists.');
                    }
                },
            ],
        ];

        $isProductOwner = $currentUser instanceof \App\Models\SuperAdmin
            && ((int) $currentUser->id === 1 || (int) ($currentUser->is_project_owner ?? 0) === 1);
        if ($request->isMethod('post') && $isProductOwner) {
            $validationRules['created_by'] = 'nullable|exists:super_admins,id';
        }

        $validated = $request->validate($validationRules);

        $roleData = [
            'name' => $name,
            'slug' => $slug,
        ];

        if ($request->isMethod('post')) {
            $superAdminId = $this->resolveCompanySuperAdminId($currentUser, $companyId);
            $creatorId = ($isProductOwner && $request->filled('created_by'))
                ? (int) $request->input('created_by')
                : ($superAdminId ?: 1);

            $roleData['created_by'] = $creatorId;
            $roleData['super_admin_id'] = $superAdminId;
            $roleData['is_default'] = 0;
            $roleData['status'] = 1;

            $role = Role::create($roleData);

            ConstructionRole::firstOrCreate(
                ['company_id' => $companyId, 'slug' => $slug],
                ['name' => $name, 'status' => 'active', 'is_system_role' => false]
            );

            $message = 'Role created successfully!';
        } else {
            $role = Role::where('uuid', $uuid)->firstOrFail();
            if ($role->isDefaultRole()) {
                return redirect()->back()->with('error', 'Default system roles cannot be edited.');
            }
            $oldSlug = $role->slug;
            $role->update($roleData);

            ConstructionRole::where('slug', $oldSlug)
                ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->update([
                    'name' => $name,
                    'slug' => $slug,
                ]);

            $message = 'Role updated successfully!';
        }

        app(\App\Services\Construction\ConstructionActivityService::class)->log(
            module: 'role_management',
            action: $uuid ? 'role_updated' : 'role_created',
            actor: $currentUser,
            reference: $role,
            description: ($uuid ? "Updated role {$name}" : "Created new role {$name}"),
            meta: [
                'role_id' => $role->id,
                'name' => $name,
                'slug' => $slug,
            ],
            request: $request
        );

        return redirect()->back()->with('success', $message);
    }

    /**
     * Update permissions for a role
     */
    public function updatePermissions(Request $request, $uuid)
    {
        $authSvc = app(\App\Services\Construction\ConstructionAuthorizationService::class);
        $actor = $authSvc->resolveActor($request);
        if (!$authSvc->hasAnyPermission($actor, ['role.assign_permissions', 'role.manage'])) {
            if (!$actor instanceof \App\Models\SuperAdmin) {
                abort(403);
            }
            return redirect()->back()->with('error', 'Unauthorized: You do not have permission to assign role permissions.');
        }

        $request->validate([
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:construction_permissions,id',
        ]);

        $role = Role::where('uuid', $uuid)->firstOrFail();

        $companyId = $this->resolveCompanyId($actor);
        if (!$this->roleBelongsToCompany($role, $companyId)) {
            abort(403);
        }

        $hasExplicitCompanyTarget = false;
        if (!$companyId && $request->filled('company_id')) {
            $companyId = (int) $request->input('company_id');
            $hasExplicitCompanyTarget = true;
        }

        $cRole = null;
        if ($companyId) {
            $cRole = ConstructionRole::where('company_id', $companyId)->where('slug', $role->slug)->first();
            if (!$cRole) {
                $template = ConstructionRole::whereNull('company_id')->where('slug', $role->slug)->first();
                $cRole = ConstructionRole::create([
                    'company_id' => $companyId,
                    'slug' => $role->slug,
                    'name' => $role->name,
                    'description' => $template?->description ?? "{$role->name} role",
                    'is_system_role' => $template?->is_system_role ?? $role->isDefaultRole(),
                    'status' => 'active',
                ]);
            }
        } else {
            $cRole = ConstructionRole::whereNull('company_id')->where('slug', $role->slug)->first();
            if (!$cRole) {
                $cRole = ConstructionRole::firstOrCreate(
                    ['company_id' => null, 'slug' => $role->slug],
                    ['name' => $role->name, 'status' => 'active', 'is_system_role' => $role->isDefaultRole()]
                );
            }
        }

        $permissionIds = array_values(array_unique(array_map('intval', $request->input('permission_ids', []))));
        $this->syncConstructionRolePermissions($cRole->id, $permissionIds);

        // When Product Owner updates a global admin/project_admin/super_admin template role,
        // propagate tenant-safe permissions down to all company-scoped counterparts.
        if (!$companyId
            && !$hasExplicitCompanyTarget
            && $cRole->company_id === null
            && in_array($role->slug, ['admin', 'project_admin', 'super_admin', 'superadmin'], true)
        ) {
            $tenantSafePermissionIds = empty($permissionIds)
                ? []
                : Permission::whereIn('id', $permissionIds)
                    ->where('slug', 'not like', 'company.%')
                    ->where('slug', 'not like', 'approval_request.%')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

            $companyRoles = ConstructionRole::whereNotNull('company_id')
                ->where('slug', $role->slug)
                ->get();

            foreach ($companyRoles as $companyRole) {
                $this->syncConstructionRolePermissions($companyRole->id, $tenantSafePermissionIds);
            }
        }

        app(\App\Services\Construction\ConstructionActivityService::class)->log(
            module: 'role_management',
            action: 'permissions_updated',
            actor: $actor,
            reference: $role,
            description: "Updated permissions for role {$role->name} (" . count($permissionIds) . " permissions)",
            meta: [
                'role_id' => $role->id,
                'permission_count' => count($permissionIds),
            ],
            request: $request
        );

        return redirect()->back()->with('success', 'Role permissions updated successfully!');
    }

    private function generateUniqueSlug($name, $uuid = null)
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $i = 2;

        while (
            Role::withTrashed()
                ->where('slug', $slug)
                ->when($uuid, fn($q) => $q->where('uuid', '!=', $uuid))
                ->exists()
        ) {
            $slug = $baseSlug . '_' . $i;
            $i++;
        }

        return $slug;
    }

    public function updateStatus(Request $request, $uuid, $status = 1)
    {
        $authSvc = app(\App\Services\Construction\ConstructionAuthorizationService::class);
        $actor = $authSvc->resolveActor($request);
        if (!$authSvc->hasAnyPermission($actor, ['role.edit', 'role.manage'])) {
            if (!$actor instanceof \App\Models\SuperAdmin) {
                abort(403);
            }
            return redirect()->back()->with('error', 'Unauthorized: You do not have permission to edit roles.');
        }

        $companyId = $this->resolveCompanyId($actor);

        try {
            $roleDetails = Role::where('uuid', $uuid)->first();
            if (!$roleDetails) {
                return redirect()->back()->with('error', 'Role not found.');
            }

            if (!$this->roleBelongsToCompany($roleDetails, $companyId)) {
                abort(403);
            }

            if ($roleDetails->isDefaultRole()) {
                return redirect()->back()->with('error', 'Default system roles cannot be modified or deactivated.');
            }

            $validatedStatus = $request->status ?? $status;
            if (!in_array($validatedStatus, [0, 1])) {
                return redirect()->back()->with('error', 'Invalid status value.');
            }
            $roleDetails->status = $validatedStatus;
            $roleDetails->save();

            ConstructionRole::where('slug', $roleDetails->slug)
                ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->update(['status' => $validatedStatus == 1 ? 'active' : 'inactive']);

            app(\App\Services\Construction\ConstructionActivityService::class)->log(
                module: 'role_management',
                action: 'status_changed',
                actor: $actor,
                reference: $roleDetails,
                description: "Changed role {$roleDetails->name} status to " . ($validatedStatus == 1 ? 'active' : 'inactive'),
                meta: [
                    'role_id' => $roleDetails->id,
                    'status' => $validatedStatus,
                ],
                request: $request
            );

            return redirect()->back()->with('success', 'Role status updated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Something went wrong while updating the role status.');
        }
    }

    public function destroy($id)
    {
        $authSvc = app(\App\Services\Construction\ConstructionAuthorizationService::class);
        $actor = $authSvc->resolveActor(request());
        if (!$authSvc->hasAnyPermission($actor, ['role.delete', 'role.manage'])) {
            if (!$actor instanceof \App\Models\SuperAdmin) {
                abort(403);
            }
            return redirect()->back()->with('error', 'Unauthorized: You do not have permission to delete roles.');
        }

        $companyId = $this->resolveCompanyId($actor);

        $role = Role::where('uuid', $id)->first();
        if ($role) {
            if (!$this->roleBelongsToCompany($role, $companyId)) {
                abort(403);
            }

            if ($role->isDefaultRole()) {
                return redirect()->back()->with('error', 'Default system roles cannot be deleted.');
            }

            ConstructionRole::where('slug', $role->slug)
                ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->delete();
            $role->delete();

            app(\App\Services\Construction\ConstructionActivityService::class)->log(
                module: 'role_management',
                action: 'role_deleted',
                actor: $actor,
                reference: $role,
                description: "Deleted role {$role->name}",
                meta: [
                    'role_id' => $role->id,
                    'name' => $role->name,
                ],
                request: request()
            );
        }

        return redirect()->back()->with('success', 'Role deleted successfully!');
    }

    /**
     * Replace permission rows on a specific ConstructionRole.
     */
    protected function syncConstructionRolePermissions(int $constructionRoleId, array $permissionIds): void
    {
        DB::table('construction_role_permissions')
            ->where('role_id', $constructionRoleId)
            ->delete();

        if (empty($permissionIds)) {
            return;
        }

        $now = now();
        $rowsToInsert = [];
        foreach ($permissionIds as $permId) {
            $rowsToInsert[] = [
                'role_id' => $constructionRoleId,
                'permission_id' => (int) $permId,
                'surface' => 'both',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('construction_role_permissions')->insert($rowsToInsert);
    }

    /**
     * Resolve the owning SuperAdmin ID for a company-scoped user.
     */
    protected function resolveCompanySuperAdminId($user, ?int $companyId): ?int
    {
        if ($user instanceof \App\Models\SuperAdmin) {
            return (int) $user->id;
        }

        if ($user instanceof \App\Models\Member) {
            if ($user->created_by && \App\Models\SuperAdmin::where('id', $user->created_by)->exists()) {
                return (int) $user->created_by;
            }
            if ($companyId) {
                $companyOwnerId = \App\Models\SuperAdmin::where('company_id', $companyId)->orderBy('id')->value('id');
                if ($companyOwnerId) {
                    return (int) $companyOwnerId;
                }
            }
            if ($user->assigned_admin_id && \App\Models\SuperAdmin::where('id', $user->assigned_admin_id)->exists()) {
                return (int) $user->assigned_admin_id;
            }
        }

        return null;
    }

    /**
     * Verify a company-scoped actor is permitted to operate on the target Role.
     */
    protected function roleBelongsToCompany(Role $role, ?int $companyId): bool
    {
        if (!$companyId || $role->isDefaultRole()) {
            return true;
        }

        $companySuperAdminIds = \App\Models\SuperAdmin::where('company_id', $companyId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return in_array((int) $role->super_admin_id, $companySuperAdminIds, true)
            || in_array((int) $role->created_by, $companySuperAdminIds, true);
    }

    /**
     * Resolve company ID from the user model.
     */
    protected function resolveCompanyId($user): ?int
    {
        if (!$user) {
            return null;
        }

        if ($user instanceof \App\Models\SuperAdmin) {
            return $user->company_id ? (int) $user->company_id : null;
        }

        if ($user instanceof \App\Models\Member) {
            if (!empty($user->company_id)) {
                return (int) $user->company_id;
            }
            if ($user->created_by) {
                $creator = \App\Models\SuperAdmin::find($user->created_by);
                if ($creator && $creator->company_id) {
                    return (int) $creator->company_id;
                }
            }
            if (!empty($user->company_name)) {
                $comp = \App\Models\Company::where('name', $user->company_name)->first();
                if ($comp) {
                    return (int) $comp->id;
                }
            }
        }

        return null;
    }
}
