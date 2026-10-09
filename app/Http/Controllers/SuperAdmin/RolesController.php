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
     * Get paginated roles with search, status, and permissions
     */
    public function list(Request $request)
    {
        // Ensure system ConstructionRole entries exist in Role table for consistent display
        $constructionRoles = ConstructionRole::all();
        foreach ($constructionRoles as $cRole) {
            Role::firstOrCreate(
                ['slug' => $cRole->slug],
                [
                    'name' => $cRole->name,
                    'status' => $cRole->status === 'active' ? 1 : 0,
                    'created_by' => auth('superadmin')->id() ?? 1,
                ]
            );
        }

        $roles = Role::with(['creator'])
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->status, function ($q) use ($request) {
                $statusValue = $request->status == 'active' ? 1 : 0;
                $q->where('status', $statusValue);
            })
            ->when($request->created_by, fn($q) => $q->where('created_by', $request->created_by))
            ->latest()
            ->paginate($request->per_page ?? 10);

        // Map assigned permission IDs & slugs to each role
        $roles->getCollection()->transform(function ($role) {
            $cRole = ConstructionRole::where('slug', $role->slug)->first();
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
     * Create or update a role
     */
    public function addRole(Request $request, $uuid = null)
    {
        $authSvc = app(\App\Services\Construction\ConstructionAuthorizationService::class);
        $actor = $authSvc->resolveActor($request);
        $requiredPermission = $uuid ? 'role.edit' : 'role.create';
        if (!$authSvc->hasAnyPermission($actor, [$requiredPermission, 'role.manage'])) {
            return redirect()->back()->with('error', 'Unauthorized: You do not have permission to modify roles.');
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

        if ($request->isMethod('post')) {
            $validationRules['created_by'] = 'required|exists:super_admins,id';
        }

        $validated = $request->validate($validationRules);

        $roleData = [
            'name' => $name,
            'slug' => $slug,
        ];

        if ($request->isMethod('post')) {
            $roleData['created_by'] = $validated['created_by'];
            $role = Role::create($roleData);

            ConstructionRole::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'status' => 'active', 'is_system_role' => false]
            );

            $message = 'Role created successfully!';
        } else {
            $role = Role::where('uuid', $uuid)->firstOrFail();
            $oldSlug = $role->slug;
            $role->update($roleData);

            ConstructionRole::where('slug', $oldSlug)->update([
                'name' => $name,
                'slug' => $slug,
            ]);

            $message = 'Role updated successfully!';
        }

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
            return redirect()->back()->with('error', 'Unauthorized: You do not have permission to assign role permissions.');
        }

        $request->validate([
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:construction_permissions,id',
        ]);

        $role = Role::where('uuid', $uuid)->firstOrFail();
        $cRole = ConstructionRole::firstOrCreate(
            ['slug' => $role->slug],
            ['name' => $role->name, 'status' => 'active', 'is_system_role' => false]
        );

        $permissionIds = $request->input('permission_ids', []);

        // Delete existing permissions for this role
        DB::table('construction_role_permissions')
            ->where('role_id', $cRole->id)
            ->delete();

        // Insert selected permissions
        $rowsToInsert = [];
        foreach ($permissionIds as $permId) {
            $rowsToInsert[] = [
                'role_id' => $cRole->id,
                'permission_id' => $permId,
                'surface' => 'both',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rowsToInsert)) {
            DB::table('construction_role_permissions')->insert($rowsToInsert);
        }

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
        try {
            $authSvc = app(\App\Services\Construction\ConstructionAuthorizationService::class);
            $actor = $authSvc->resolveActor($request);
            if (!$authSvc->hasAnyPermission($actor, ['role.edit', 'role.manage'])) {
                return redirect()->back()->with('error', 'Unauthorized: You do not have permission to edit roles.');
            }

            $roleDetails = Role::where('uuid', $uuid)->first();
            if (!$roleDetails) {
                return redirect()->back()->with('error', 'Role not found.');
            }
            $validatedStatus = $request->status ?? $status;
            if (!in_array($validatedStatus, [0, 1])) {
                return redirect()->back()->with('error', 'Invalid status value.');
            }
            $roleDetails->status = $validatedStatus;
            $roleDetails->save();

            ConstructionRole::where('slug', $roleDetails->slug)
                ->update(['status' => $validatedStatus == 1 ? 'active' : 'inactive']);

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
            return redirect()->back()->with('error', 'Unauthorized: You do not have permission to delete roles.');
        }

        $role = Role::where('uuid', $id)->first();
        if ($role) {
            ConstructionRole::where('slug', $role->slug)->delete();
            $role->delete();
        }

        return redirect()->back()->with('success', 'Role deleted successfully!');
    }
}
