<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Role;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SuperAdminManageController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = Auth::guard('superadmin')->user()
            ?? Auth::guard('admin')->user()
            ?? Auth::guard('member')->user()
            ?? Auth::guard('callingteam')->user()
            ?? Auth::user();

        $isProjectOwner = false;
        if (Auth::guard('superadmin')->check() && $currentUser instanceof SuperAdmin) {
            $isProjectOwner = ((int) $currentUser->id === 1 || (string) $currentUser->id === '1' || (isset($currentUser->is_project_owner) && (int) $currentUser->is_project_owner === 1));
        }

        $authSvc = app(\App\Services\Construction\ConstructionAuthorizationService::class);
        if (!$isProjectOwner && !$authSvc->hasAnyPermission($currentUser, ['super_admin.view', 'super_admin.manage', 'super_admin.create', 'super_admin.edit'])) {
            abort(403, 'You do not have permission to view Admin Account Management.');
        }

        $superAdmins = SuperAdmin::query()
            ->with('company')
            ->when(!$isProjectOwner && $currentUser, function ($q) use ($currentUser) {
                if ($currentUser instanceof SuperAdmin) {
                    if ($currentUser->company_id) {
                        $q->where('company_id', $currentUser->company_id);
                    } else {
                        $q->where('id', $currentUser->id);
                    }
                } elseif ($currentUser instanceof Member) {
                    if ($currentUser->created_by) {
                        $creator = SuperAdmin::find($currentUser->created_by);
                        if ($creator && $creator->company_id) {
                            $q->where('company_id', $creator->company_id);
                        } else {
                            $q->where('id', $currentUser->created_by);
                        }
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }
            })
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhereHas('company', function ($cq) use ($search) {
                          $cq->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->latest()
            ->get();

        // Admins are Members with role ID 1, slug 'admin', or having active role assignments
        $assignedMemberIds = \App\Models\MemberRoleAssignment::where('status', 1)->pluck('member_id')->toArray();

        $admins = Member::query()
            ->where(function ($q) use ($assignedMemberIds) {
                $q->whereJsonContains('roles', '1')
                  ->orWhereJsonContains('roles', 1)
                  ->orWhere('registration_source', 'admin_created')
                  ->orWhere('slug', 'like', 'admin%');

                if (!empty($assignedMemberIds)) {
                    $q->orWhereIn('id', $assignedMemberIds);
                }
            })
            ->when(!$isProjectOwner && $currentUser, function ($q) use ($currentUser) {
                if ($currentUser instanceof SuperAdmin) {
                    $q->where('created_by', $currentUser->id);
                } elseif ($currentUser instanceof Member) {
                    $creatorId = $currentUser->created_by ?: $currentUser->id;
                    $q->where(function ($sq) use ($currentUser, $creatorId) {
                        $sq->where('created_by', $creatorId)
                          ->orWhere('created_by', $currentUser->id)
                          ->orWhere('assigned_admin_id', $currentUser->id)
                          ->orWhere('id', $currentUser->id);
                    });
                }
            })
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            })
            ->latest()
            ->get();

        $superAdminsMap = SuperAdmin::with('company')->get()->keyBy('id');
        $allMembersMap = Member::get(['id', 'created_by', 'company_name'])->keyBy('id');

        $admins->transform(function ($admin) use ($superAdminsMap, $allMembersMap) {
            $creatorCompany = null;
            $fallbackCompanyName = $admin->company_name;
            $current = $admin;
            $visited = [];

            while ($current && $current->created_by && !in_array($current->created_by, $visited)) {
                $visited[] = $current->created_by;

                $superAdmin = $superAdminsMap->get($current->created_by);
                if ($superAdmin && $superAdmin->company) {
                    $creatorCompany = $superAdmin->company;
                    break;
                }

                $current = $allMembersMap->get($current->created_by);
                if ($current && empty($fallbackCompanyName) && !empty($current->company_name)) {
                    $fallbackCompanyName = $current->company_name;
                }
            }

            if ($creatorCompany) {
                $admin->setRelation('company', $creatorCompany);
            } elseif (!empty($fallbackCompanyName)) {
                $comp = new \App\Models\Company();
                $comp->name = $fallbackCompanyName;
                $admin->setRelation('company', $comp);
            }

            return $admin;
        });

        $constructionRoles = \App\Models\ConstructionRole::where('status', 'active')->get();
        $legacyRoles = \App\Models\Role::where('status', 1)->get();
        $companies = \App\Models\Company::where('status', 'active')->get();

        return Inertia::render('SuperAdmin/SuperAdmins/Index', [
            'superAdmins' => $superAdmins,
            'admins' => $admins,
            'companies' => $companies,
            'constructionRoles' => $constructionRoles,
            'legacyRoles' => $legacyRoles,
            'filters' => $request->only(['search']),
        ]);
    }

    public function storeSuperAdmin(Request $request)
    {
        $currentUser = Auth::guard('superadmin')->user() ?? Auth::user();
        $isProjectOwner = $currentUser ? ((int) $currentUser->id === 1 || (string) $currentUser->id === '1' || (isset($currentUser->is_project_owner) && $currentUser->is_project_owner == 1)) : false;

        if (!$isProjectOwner) {
            return back()->with('error', 'Only the Project Owner can create new Super Admin accounts.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:super_admins,email|unique:members,email',
            'username' => 'nullable|string|max:255|unique:super_admins,username|unique:members,username',
            'phone' => 'nullable|string|max:15|unique:super_admins,phone|unique:members,phone',
            'password' => 'required|string|min:6|confirmed',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',

            // Merged company creation details
            'company_name' => 'required|string|max:255',
            'company_legal_name' => 'nullable|string|max:255',
            'company_email' => 'nullable|email|max:255',
            'company_phone' => 'nullable|string|max:15',
            'company_gst_number' => 'nullable|string|max:50',
            'company_address' => 'nullable|string',
        ]);

        $imagePath = null;
        if ($request->hasFile('profile_image')) {
            $imagePath = $request->file('profile_image')->store('superadmins_photos', 'public');
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $imagePath) {
                $superAdmin = SuperAdmin::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'username' => $request->username ?: explode('@', $request->email)[0],
                    'phone' => $request->phone ?: null,
                    'password' => Hash::make($request->password),
                    'status' => 1,
                    'profile_image' => $imagePath,
                    'roles' => ['superadmin'],
                ]);

                $company = \App\Models\Company::create([
                    'name' => $request->company_name,
                    'legal_name' => $request->company_legal_name ?: $request->company_name,
                    'email' => $request->company_email ?: $request->email,
                    'phone' => $request->company_phone ?: $request->phone,
                    'gst_number' => $request->company_gst_number,
                    'address' => $request->company_address,
                    'status' => 'active',
                    'created_by_type' => SuperAdmin::class,
                    'created_by_id' => $superAdmin->id,
                ]);

                $superAdmin->update(['company_id' => $company->id]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), '1062')) {
                return back()->withErrors([
                    'phone' => 'This phone number or email is already registered in the system.',
                ])->withInput();
            }
            return back()->withErrors([
                'email' => 'Failed to create Super Admin account due to database error: ' . $e->getMessage(),
            ])->withInput();
        }

        return back()->with('success', 'Super Admin account and company created successfully.');
    }

    public function storeAdmin(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:members,email|unique:super_admins,email',
            'phone' => 'nullable|string|max:15|unique:members,phone|unique:super_admins,phone',
            'password' => 'required|string|min:6|confirmed',
            'role_slug' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('members_photos', 'public');
        }

        $roleSlug = strtolower($request->role_slug ?: 'admin');
        $constructionRole = \App\Models\ConstructionRole::whereIn('slug', [$roleSlug, 'admin', 'project_admin'])->first();

        $adminRole = Role::where('id', 1)->orWhere('slug', 'admin')->first();
        $adminRoleId = $adminRole ? $adminRole->id : 1;

        $superAdminId = Auth::guard('superadmin')->id() ?? 1;

        $baseUsername = \Illuminate\Support\Str::slug(explode('@', $request->email)[0], '_') ?: 'admin';
        $username = $baseUsername;
        $counter = 1;
        while (Member::where('username', $username)->exists() || SuperAdmin::where('username', $username)->exists()) {
            $username = $baseUsername . '_' . $counter;
            $counter++;
        }

        try {
            $member = Member::create([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $username,
                'phone' => $request->phone ?: null,
                'password' => Hash::make($request->password),
                'status' => 1, // Active
                'roles' => [$adminRoleId],
                'image' => $imagePath,
                'slug' => \Illuminate\Support\Str::slug($request->name . '-' . \Illuminate\Support\Str::random(6)),
                'registration_source' => 'admin_created',
                'created_by' => $superAdminId,
                'approved_by' => $superAdminId,
                'approved_at' => now(),
            ]);

            if ($constructionRole) {
                \Illuminate\Support\Facades\DB::table('construction_member_role_assignments')->updateOrInsert(
                    [
                        'member_id' => $member->id,
                        'role_id' => $constructionRole->id,
                    ],
                    [
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), '1062')) {
                return back()->withErrors([
                    'phone' => 'This phone number or email is already registered in the system.',
                ])->withInput();
            }
            return back()->withErrors([
                'email' => 'Failed to create Admin account due to database error: ' . $e->getMessage(),
            ])->withInput();
        }

        return back()->with('success', 'Admin account created successfully.');
    }

    public function toggleStatusSuperAdmin(SuperAdmin $superAdmin)
    {
        $superAdmin->update([
            'status' => (int)$superAdmin->status === 1 ? 0 : 1,
        ]);

        return back()->with('success', 'Super Admin account status updated.');
    }

    public function toggleStatusAdmin(Member $member)
    {
        $member->update([
            'status' => (int)$member->status === 1 ? 0 : 1,
        ]);

        return back()->with('success', 'Admin account status updated.');
    }

    public function updatePasswordSuperAdmin(Request $request, SuperAdmin $superAdmin)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $superAdmin->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Super Admin password updated successfully.');
    }

    public function updatePasswordAdmin(Request $request, Member $member)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $member->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Admin password updated successfully.');
    }

    public function updateRoleSuperAdmin(Request $request, SuperAdmin $superAdmin)
    {
        $request->validate([
            'role_slug' => 'required|string',
        ]);

        $superAdmin->update([
            'roles' => [$request->role_slug],
        ]);

        return back()->with('success', 'Super Admin role updated successfully.');
    }

    public function updateRoleAdmin(Request $request, Member $member)
    {
        $request->validate([
            'role_slug' => 'required|string',
        ]);

        $roleSlug = strtolower($request->role_slug);
        $roleObj = \App\Models\ConstructionRole::where('slug', $roleSlug)->first();

        if ($roleObj) {
            \Illuminate\Support\Facades\DB::table('construction_member_role_assignments')
                ->where('member_id', $member->id)
                ->delete();

            \Illuminate\Support\Facades\DB::table('construction_member_role_assignments')->insert([
                'member_id' => $member->id,
                'role_id' => $roleObj->id,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Admin account role updated successfully.');
    }
}
