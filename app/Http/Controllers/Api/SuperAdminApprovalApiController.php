<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConstructionRole;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Member;
use App\Models\Role;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SuperAdminApprovalApiController extends Controller
{
    public function approvalStats(Request $request)
    {
        $pendingCount = Member::pending()->selfRegistered()->count();
        $approvedCount = Member::approved()->count();
        $rejectedCount = Member::rejected()->count();
        $selfRegisteredCount = Member::selfRegistered()->count();
        $totalMembers = Member::count();

        return response()->json([
            'success' => true,
            'stats' => [
                'pending' => $pendingCount,
                'approved' => $approvedCount,
                'rejected' => $rejectedCount,
                'self_registered' => $selfRegisteredCount,
                'total' => $totalMembers,
                'approval_rate' => $totalMembers > 0
                    ? round(($approvedCount / $totalMembers) * 100, 2)
                    : 0,
            ],
        ]);
    }

    public function pendingList(Request $request)
    {
        $perPage = $request->input('per_page', 15);

        $members = Member::pending()
            ->with(['approver:id,name', 'employee'])
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('name', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%")
                        ->orWhere('phone', 'like', "%{$request->search}%")
                        ->orWhere('company_name', 'like', "%{$request->search}%");
                });
            })
            ->when($request->state, fn($q) => $q->where('state', $request->state))
            ->when($request->city, fn($q) => $q->where('city', $request->city))
            ->when($request->source, function ($q) use ($request) {
                if ($request->source === 'web') return $q->where('registration_source', 'web');
                if ($request->source === 'mobile') return $q->where('registration_source', 'mobile_api');
            })
            ->latest('created_at')
            ->paginate($perPage);

        $members->getCollection()->transform(function ($m) {
            return $this->transformMember($m);
        });

        return response()->json([
            'success' => true,
            'members' => $members,
            'filters' => $request->only(['search', 'state', 'city', 'source', 'per_page']),
        ]);
    }

    public function approvedList(Request $request)
    {
        $perPage = $request->input('per_page', 15);

        $members = Member::approved()
            ->with(['approver:id,name', 'assignedAdmin:id,name', 'employee'])
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('name', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%")
                        ->orWhere('phone', 'like', "%{$request->search}%")
                        ->orWhere('company_name', 'like', "%{$request->search}%");
                });
            })
            ->latest('approved_at')
            ->paginate($perPage);

        $members->getCollection()->transform(function ($m) {
            return $this->transformMember($m);
        });

        return response()->json([
            'success' => true,
            'members' => $members,
        ]);
    }

    public function rejectedList(Request $request)
    {
        $perPage = $request->input('per_page', 15);

        $members = Member::rejected()
            ->with(['approver:id,name', 'employee'])
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('name', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%")
                        ->orWhere('phone', 'like', "%{$request->search}%");
                });
            })
            ->latest('rejected_at')
            ->paginate($perPage);

        $members->getCollection()->transform(function ($m) {
            return $this->transformMember($m);
        });

        return response()->json([
            'success' => true,
            'members' => $members,
        ]);
    }

    public function show(Request $request, Member $member)
    {
        $member->loadMissing(['approver:id,name', 'assignedAdmin:id,name', 'employee']);

        return response()->json([
            'success' => true,
            'member' => $this->transformMember($member),
            'available_roles' => $this->getRoleOptions(),
            'registration_details' => [
                'source' => $member->registration_source,
                'source_label' => match($member->registration_source) {
                    'web' => 'Web Portal',
                    'mobile_api' => 'Mobile App',
                    'admin_created' => 'Admin Created',
                    default => 'Unknown',
                },
                'ip_address' => null,
                'user_agent' => null,
            ],
        ]);
    }

    public function approve(Request $request, Member $member)
    {
        if (! $member->isPending() && ! $member->isRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending or rejected members can be approved.',
            ], 422);
        }

        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['integer', 'exists:departments,id'],
            'designations' => ['nullable', 'array'],
            'designations.*' => ['integer', 'exists:designations,id'],
            'assigned_admin_id' => ['nullable', 'integer', Rule::exists('members', 'id')],
            'approval_remark' => ['nullable', 'string', 'max:1000'],
            'send_notification' => ['nullable', 'boolean'],
        ]);

        $approverId = $this->resolveApproverId($request);

        try {
            $member->update([
                'status' => Member::STATUS_ACTIVE,
                'approved_by' => $approverId,
                'approved_at' => now(),
                'rejected_at' => null,
                'approval_remark' => $validated['approval_remark'] ?? null,
                'roles' => array_map('intval', $validated['roles']),
                'departments' => isset($validated['departments'])
                    ? array_map('intval', $validated['departments'])
                    : [],
                'designation' => isset($validated['designations'])
                    ? array_map('intval', $validated['designations'])
                    : [],
                'assigned_admin_id' => $validated['assigned_admin_id'] ?? null,
            ]);

            Employee::firstOrCreate(
                ['member_id' => $member->id],
                ['uuid' => (string) Str::uuid()]
            );

            $this->syncSuperAdminIfNeeded($member, $validated['roles']);

            Log::info('Member approved via API.', [
                'member_id' => $member->id,
                'member_uuid' => $member->uuid,
                'approved_by' => $approverId,
                'roles' => $validated['roles'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Member approved successfully. Construction roles assigned.',
                'member' => $this->transformMember($member->fresh()),
                'next_steps' => [
                    'Member can now log in with their password.',
                    'Assign to construction projects via Project Team.',
                    'Assign to survey teams via Survey Planning.',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to approve member.', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve member: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, Member $member)
    {
        if (! $member->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending members can be rejected.',
            ], 422);
        }

        $validated = $request->validate([
            'approval_remark' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $approverId = $this->resolveApproverId($request);

        try {
            $member->update([
                'status' => Member::STATUS_REJECTED,
                'approved_by' => $approverId,
                'rejected_at' => now(),
                'approval_remark' => $validated['approval_remark'],
            ]);

            Log::info('Member rejected via API.', [
                'member_id' => $member->id,
                'member_uuid' => $member->uuid,
                'rejected_by' => $approverId,
                'reason' => $validated['approval_remark'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registration rejected. Member has been notified via status check.',
                'member' => $this->transformMember($member->fresh()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reject member.', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject member: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function assignAdmin(Request $request, Member $member)
    {
        $validated = $request->validate([
            'admin_id' => ['required', 'integer'],
        ]);

        $admin = Member::approved()
            ->where('id', $validated['admin_id'])
            ->where(function ($q) {
                $q->whereJsonContains('roles', '1')
                    ->orWhereJsonContains('roles', 1);
            })
            ->first();

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Selected admin is invalid or not an active admin.',
            ], 422);
        }

        $memberRoles = is_array($member->roles) ? $member->roles : [];
        if (in_array(1, $memberRoles, true) || in_array(2, $memberRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only regular construction members can be assigned to admins.',
            ], 422);
        }

        $member->update([
            'assigned_admin_id' => $admin->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Member assigned to admin successfully.',
            'member' => $this->transformMember($member->fresh()),
        ]);
    }

    public function constructionRoles(Request $request)
    {
        $roles = Role::where('status', 1)
            ->get(['id', 'name', 'slug', 'description'])
            ->map(function ($r) {
                $constructionMap = [
                    'driver' => 'Driver — Point-to-Point, Danda Pakeden, Multi-Day allocations',
                    'surveyor' => 'Surveyor — Team Formation, Work Division Type A/B',
                    'draftsman' => 'Draftsman — Draft Creation, Senior direct approval',
                    'supervisor' => 'Supervisor — Client Review, Phase-wise Billing, Revision Loop',
                    'admin' => 'Admin — Panel Access, Member Management',
                    'super-admin' => 'Super Admin — Full System Access',
                ];

                return [
                    'id' => (int) $r->id,
                    'name' => $r->name,
                    'slug' => $r->slug,
                    'construction_role' => true,
                    'description' => $constructionMap[strtolower($r->slug)]
                        ?? ($r->description ?? 'General construction role'),
                    'phase_compatibility' => match(strtolower($r->slug)) {
                        'driver' => 'Phase 2 (Driver Allocation)',
                        'surveyor' => 'Phase 3 (Survey Team)',
                        'draftsman' => 'Phase 4 (Drafting & Approval)',
                        'supervisor' => 'Phase 5-6 (Execution + Client Review)',
                        'admin' => 'All Phases (Management)',
                        default => 'All Phases',
                    },
                ];
            });

        return response()->json([
            'success' => true,
            'roles' => $roles,
            'construction_role_mapping' => [
                'phase_2_driver_allocation' => [
                    'point_to_point' => 'Driver — Point-to-Point',
                    'danda_pakeden' => 'Driver — Tools/Material Handling',
                    'multi_day' => 'Driver — Multi-Day Booked (Weseta/Kahan)',
                ],
                'phase_3_survey_teams' => [
                    'type_a_maint_machine' => 'Survey Team — Heavy Machinery (Type A)',
                    'type_b_danda_pakden' => 'Survey Team — Physical Marking/Stakes (Type B)',
                    'surveyor_draftsman_overlap' => 'Skilled Surveyor can perform Drafting (Small changes)',
                ],
                'phase_4_drafting' => [
                    'junior_draftsman' => 'Needs Senior review before approval',
                    'senior_draftsman' => 'Direct Approve — skips junior review',
                    'rejection_loop' => 'Wrong drafts sent back for correction',
                ],
                'phase_6_client_review' => [
                    'supervisor_completion' => 'Supervisor checks 100% field + drafting completion',
                    'ready_for_review' => 'Supervisor marks Ready for Review',
                    'client_approve' => 'Project moves to Final Closure',
                    'client_reject' => 'Enters Revision Phase Loop',
                ],
            ],
        ]);
    }

    public function allDepartments(Request $request)
    {
        $departments = Department::where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return response()->json([
            'success' => true,
            'departments' => $departments,
        ]);
    }

    public function designationsByDepartments(Request $request)
    {
        $departmentIds = $request->input('department_ids', []);
        if (! is_array($departmentIds)) {
            $departmentIds = [$departmentIds];
        }
        $departmentIds = array_filter($departmentIds, fn($id) => is_numeric($id) && $id > 0);

        if (empty($departmentIds)) {
            return response()->json([
                'success' => true,
                'designations' => [],
            ]);
        }

        $designations = Designation::whereIn('department_id', $departmentIds)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);

        return response()->json([
            'success' => true,
            'designations' => $designations,
        ]);
    }

    private function transformMember(Member $member): array
    {
        $departmentIds = is_array($member->departments) ? $member->departments : [];
        $designationIds = is_array($member->designation) ? $member->designation : [];
        $roleIds = is_array($member->roles) ? $member->roles : [];

        return [
            'id' => $member->id,
            'uuid' => $member->uuid,
            'name' => $member->name,
            'username' => $member->username,
            'email' => $member->email,
            'phone' => $member->phone,
            'company_name' => $member->company_name,
            'state' => $member->state,
            'city' => $member->city,
            'image' => $member->profile_photo_url,
            'status' => (int) $member->status,
            'status_text' => match((int) $member->status) {
                Member::STATUS_PENDING => 'Pending Approval',
                Member::STATUS_ACTIVE => 'Active',
                Member::STATUS_REJECTED => 'Rejected',
                default => 'Unknown',
            },
            'status_badge_class' => match((int) $member->status) {
                Member::STATUS_PENDING => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                Member::STATUS_ACTIVE => 'bg-green-100 text-green-800 border-green-200',
                Member::STATUS_REJECTED => 'bg-red-100 text-red-800 border-red-200',
                default => 'bg-gray-100 text-gray-800 border-gray-200',
            },
            'registration_source' => $member->registration_source,
            'registration_source_text' => match($member->registration_source) {
                'web' => 'Web',
                'mobile_api' => 'Mobile App',
                'admin_created' => 'Admin Created',
                default => $member->registration_source,
            },
            'roles' => $roleIds,
            'role_names' => ! empty($roleIds)
                ? Role::whereIn('id', $roleIds)->pluck('name')->implode(', ')
                : 'Not Assigned',
            'departments' => $departmentIds,
            'department_names' => ! empty($departmentIds)
                ? Department::whereIn('id', $departmentIds)->pluck('name')->implode(', ')
                : null,
            'designations' => $designationIds,
            'designation_names' => ! empty($designationIds)
                ? Designation::whereIn('id', $designationIds)->pluck('name')->implode(', ')
                : null,
            'assigned_admin_id' => $member->assigned_admin_id,
            'assigned_admin_name' => $member->assignedAdmin?->name,
            'approved_by_id' => $member->approved_by,
            'approved_by_name' => $member->approver?->name,
            'approval_remark' => $member->approval_remark,
            'is_calling_team' => (bool) $member->is_calling_team,
            'must_change_password' => (bool) $member->must_change_password,
            'phone_verified' => ! empty($member->phone_verify_at),
            'employee_id' => $member->employee?->employee_id,
            'employee_uuid' => $member->employee?->uuid,
            'alternate_number' => $member->employee?->alternate_number,
            'aadhaar_number' => $member->employee?->aadhaar_number,
            'pan_number' => $member->employee?->pan_number,
            'created_at' => optional($member->created_at)->toISOString(),
            'approved_at' => optional($member->approved_at)->toISOString(),
            'rejected_at' => optional($member->rejected_at)->toISOString(),
        ];
    }

    private function resolveApproverId(Request $request): ?int
    {
        $user = $request->user();
        if ($user instanceof SuperAdmin) {
            return $user->id;
        }
        if ($user instanceof Member) {
            $roleIds = is_array($user->roles) ? $user->roles : [];
            if (in_array(2, $roleIds, true)) {
                $superAdmin = SuperAdmin::where('phone', $user->phone)->first();
                return $superAdmin?->id ?? SuperAdmin::query()->value('id');
            }
        }

        return SuperAdmin::query()->value('id');
    }

    private function getRoleOptions(): array
    {
        return Role::where('status', 1)
            ->get(['id', 'name', 'slug'])
            ->map(fn($r) => [
                'value' => (int) $r->id,
                'label' => $r->name,
                'slug' => $r->slug,
            ])
            ->toArray();
    }

    private function syncSuperAdminIfNeeded(Member $member, array $roleIds): void
    {
        if (! in_array(2, array_map('intval', $roleIds), true)) {
            return;
        }

        $existing = SuperAdmin::where('phone', $member->phone)->first();
        if ($existing) {
            return;
        }

        SuperAdmin::create([
            'name' => $member->name,
            'roles' => 'super',
            'phone' => $member->phone,
            'whatsapp_phone' => $member->phone,
            'email' => $member->email,
            'status' => 1,
            'username' => $member->username,
            'password' => $member->password,
        ]);
    }
}
