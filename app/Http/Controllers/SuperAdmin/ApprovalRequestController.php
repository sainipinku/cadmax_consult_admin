<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActionApprovalRequest;
use App\Models\ConstructionVehicle;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\Member;
use App\Models\Project;
use App\Models\SurveySubmission;
use App\Models\Task;
use App\Models\Vehicle;
use App\Models\Permission;
use App\Models\ConstructionRole;
use App\Models\MemberRoleAssignment;
use App\Models\ActivityLog;
use App\Enums\ActionTypeEnum;
use App\Services\Construction\ConstructionActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ApprovalRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = ActionApprovalRequest::query()->latest();

        if ($request->has('status') && $request->status !== 'all' && $request->status !== null) {
            $query->where('status', (int) $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('requester_name', 'like', "%{$search}%")
                  ->orWhere('resource_name', 'like', "%{$search}%")
                  ->orWhere('resource_type', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $requests = $query->paginate(15)->withQueryString();

        $counts = [
            'all' => ActionApprovalRequest::count(),
            'pending' => ActionApprovalRequest::pending()->count(),
            'approved' => ActionApprovalRequest::approved()->count(),
            'rejected' => ActionApprovalRequest::rejected()->count(),
        ];

        return Inertia::render('SuperAdmin/ApprovalRequests/Index', [
            'requests' => $requests,
            'counts' => $counts,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function submitPermissionRequest(Request $request)
    {
        $request->validate([
            'permission_slug' => 'required|string',
            'permission_name' => 'nullable|string',
            'module_name' => 'nullable|string',
            'reason' => 'required|string',
        ]);

        $user = Auth::guard('admin')->user()
            ?? Auth::guard('superadmin')->user()
            ?? Auth::guard('member')->user()
            ?? Auth::user();

        $permSlug = $request->permission_slug;
        $permName = $request->permission_name ?? $permSlug;
        $module = $request->module_name ?? 'General';

        // Check if existing pending request exists for this user and permission
        $existing = ActionApprovalRequest::where('action', 'permission')
            ->where('requester_id', $user ? $user->id : '0')
            ->where('resource_id', $permSlug)
            ->where('status', ActionApprovalRequest::STATUS_PENDING)
            ->first();

        if ($existing) {
            return back()->with('info', 'A permission request for this item is already pending Super Admin approval.');
        }

        $appReq = ActionApprovalRequest::create([
            'requester_id' => $user ? $user->id : '0',
            'requester_type' => Auth::guard('superadmin')->check() ? 'superadmin' : (Auth::guard('admin')->check() ? 'admin' : 'member'),
            'requester_name' => $user ? $user->name : 'User',
            'requester_email' => $user ? $user->email : null,
            'action' => 'permission',
            'resource_type' => $module,
            'resource_id' => $permSlug,
            'resource_name' => "Permission: {$permName} ({$permSlug})",
            'reason' => $request->reason,
            'status' => ActionApprovalRequest::STATUS_PENDING,
        ]);

        try {
            app(ConstructionActivityService::class)->log(
                module: 'ApprovalRequest',
                action: 'submit_permission_request',
                actor: $user,
                meta: [
                    'request_id' => $appReq->id,
                    'permission_slug' => $permSlug,
                    'permission_name' => $permName,
                    'reason' => $request->reason,
                ],
                request: $request
            );
        } catch (\Throwable $e) {}

        return back()->with('success', 'Permission request submitted to Super Admin for approval.');
    }

    public function submitDeleteRequest(Request $request)
    {
        $request->validate([
            'resource_type' => 'required|string',
            'resource_id' => 'required|string',
            'resource_name' => 'nullable|string',
            'reason' => 'nullable|string',
        ]);

        $user = Auth::guard('admin')->user()
            ?? Auth::guard('superadmin')->user()
            ?? Auth::guard('member')->user()
            ?? Auth::user();

        $resourceType = strtolower($request->resource_type);
        $resourceId = (string) $request->resource_id;

        $existing = ActionApprovalRequest::where('action', 'delete')
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('status', ActionApprovalRequest::STATUS_PENDING)
            ->first();

        if ($existing) {
            return back()->with('info', 'A deletion request for this item is already pending Super Admin approval.');
        }

        $appReq = ActionApprovalRequest::create([
            'requester_id' => $user ? $user->id : '0',
            'requester_type' => Auth::guard('superadmin')->check() ? 'superadmin' : 'admin',
            'requester_name' => $user ? $user->name : 'Admin',
            'requester_email' => $user ? $user->email : null,
            'action' => 'delete',
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'resource_name' => $request->resource_name ?? "{$request->resource_type} #{$resourceId}",
            'reason' => $request->reason ?? 'Admin requested deletion of ' . $request->resource_type,
            'status' => ActionApprovalRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'Delete request submitted to Super Admin for approval.');
    }

    public function approve(Request $request, ActionApprovalRequest $approvalRequest)
    {
        if ((int) $approvalRequest->status !== ActionApprovalRequest::STATUS_PENDING) {
            return back()->with('error', 'This request has already been processed.');
        }

        $superAdmin = Auth::guard('superadmin')->user();
        $adminRemark = $request->input('admin_remark', 'Approved by Super Admin.');

        $approvalRequest->update([
            'status' => ActionApprovalRequest::STATUS_APPROVED,
            'reviewed_by' => $superAdmin ? $superAdmin->id : null,
            'reviewed_at' => now(),
            'admin_remark' => $adminRemark,
        ]);

        // If this is a permission request, grant the permission to the requester's role
        if ($approvalRequest->action === 'permission') {
            $permSlug = $approvalRequest->resource_id;
            $perm = Permission::where('slug', $permSlug)->first();

            if ($perm && $approvalRequest->requester_id) {
                $member = Member::find($approvalRequest->requester_id);
                if ($member) {
                    $activeRoleIds = MemberRoleAssignment::where('member_id', $member->id)
                        ->where('status', 1)
                        ->pluck('role_id');

                    if ($activeRoleIds->isEmpty()) {
                        $defaultRole = ConstructionRole::where('slug', 'site_employee')->first();
                        if ($defaultRole) {
                            $activeRoleIds = collect([$defaultRole->id]);
                        }
                    }

                    foreach ($activeRoleIds as $rId) {
                        DB::table('construction_role_permissions')->updateOrInsert(
                            ['role_id' => $rId, 'permission_id' => $perm->id],
                            ['surface' => 'both', 'created_at' => now(), 'updated_at' => now()]
                        );
                    }
                }
            }
            return back()->with('success', 'Permission request approved and permission granted successfully!');
        }

        // If requested to execute immediate deletion now:
        if ($request->boolean('execute_now')) {
            $this->executeTargetDeletion($approvalRequest);
            return back()->with('success', 'Delete request approved and resource deleted immediately.');
        }

        return back()->with('success', 'Delete permission approved. The requester can now execute the action.');
    }

    public function reject(Request $request, ActionApprovalRequest $approvalRequest)
    {
        if ((int) $approvalRequest->status !== ActionApprovalRequest::STATUS_PENDING) {
            return back()->with('error', 'This request has already been processed.');
        }

        $superAdmin = Auth::guard('superadmin')->user();
        $adminRemark = $request->input('admin_remark', 'Rejected by Super Admin.');

        $approvalRequest->update([
            'status' => ActionApprovalRequest::STATUS_REJECTED,
            'reviewed_by' => $superAdmin ? $superAdmin->id : null,
            'reviewed_at' => now(),
            'admin_remark' => $adminRemark,
        ]);

        return back()->with('success', 'Request rejected successfully.');
    }

    protected function executeTargetDeletion(ActionApprovalRequest $approvalRequest): void
    {
        $resourceType = strtolower($approvalRequest->resource_type);
        $resourceId = $approvalRequest->resource_id;

        try {
            $modelClass = match ($resourceType) {
                'employee' => Employee::class,
                'vehicle' => Vehicle::class ?? ConstructionVehicle::class,
                'constructionvehicle' => ConstructionVehicle::class,
                'member' => Member::class,
                'project' => Project::class,
                'task' => Task::class,
                'document' => Document::class,
                'surveysubmission' => SurveySubmission::class,
                'equipment' => Equipment::class,
                default => null,
            };

            if ($modelClass) {
                $instance = $modelClass::where('id', $resourceId)->orWhere('uuid', $resourceId)->first();
                if ($instance) {
                    $instance->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to auto-execute approved deletion: ' . $e->getMessage());
        }
    }
}
