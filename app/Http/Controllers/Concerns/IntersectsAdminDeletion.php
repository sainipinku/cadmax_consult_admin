<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ActionApprovalRequest;
use Illuminate\Support\Facades\Auth;

trait IntersectsAdminDeletion
{
    /**
     * Determine if current user deletion requires Super Admin approval.
     */
    protected function requiresSuperAdminApproval(string $resourceType = '', string|int $resourceId = ''): bool
    {
        // If session is impersonating / emulating an admin or member, enforce admin deletion approval!
        if (session()->has('impersonator')) {
            if (Auth::guard('admin')->check() || Auth::guard('member')->check()) {
                if ($resourceType !== '' && $resourceId !== '') {
                    return !$this->hasApprovedDeletePermission($resourceType, $resourceId);
                }
                return true;
            }
        }

        // Super Admin guard acting as themselves has direct deletion privileges
        if (Auth::guard('superadmin')->check()) {
            return false;
        }

        // Admin guard or non-superadmin users: check if an approved delete request exists
        if ($resourceType !== '' && $resourceId !== '') {
            return !$this->hasApprovedDeletePermission($resourceType, $resourceId);
        }

        return true;
    }

    /**
     * Check if an approved delete request exists for this resource.
     */
    protected function hasApprovedDeletePermission(string $resourceType, string|int $resourceId): bool
    {
        return ActionApprovalRequest::where('action', 'delete')
            ->where('resource_type', strtolower($resourceType))
            ->where('resource_id', (string) $resourceId)
            ->where('status', ActionApprovalRequest::STATUS_APPROVED)
            ->exists();
    }

    /**
     * Cleanup approved delete request after deletion is executed.
     */
    protected function consumeDeleteApprovalPermission(string $resourceType, string|int $resourceId): void
    {
        ActionApprovalRequest::where('action', 'delete')
            ->where('resource_type', strtolower($resourceType))
            ->where('resource_id', (string) $resourceId)
            ->delete();
    }

    /**
     * Submit a deletion approval request to Super Admin.
     */
    protected function requestDeleteApproval(string $resourceType, string|int $resourceId, ?string $resourceName = null, ?string $reason = null)
    {
        $user = Auth::guard('admin')->user() ?? Auth::guard('member')->user() ?? Auth::user();

        $existing = ActionApprovalRequest::where('action', 'delete')
            ->where('resource_type', strtolower($resourceType))
            ->where('resource_id', (string) $resourceId)
            ->where('status', ActionApprovalRequest::STATUS_PENDING)
            ->first();

        if ($existing) {
            return back()->with('info', 'A deletion request for this item is already pending Super Admin approval.');
        }

        ActionApprovalRequest::create([
            'requester_id' => $user ? $user->id : '0',
            'requester_type' => 'admin',
            'requester_name' => $user ? $user->name : 'Admin',
            'requester_email' => $user ? $user->email : null,
            'action' => 'delete',
            'resource_type' => strtolower($resourceType),
            'resource_id' => (string) $resourceId,
            'resource_name' => $resourceName ?? "{$resourceType} #{$resourceId}",
            'reason' => $reason ?? request('reason', 'Admin requested deletion of ' . $resourceType),
            'status' => ActionApprovalRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'Delete request submitted to Super Admin for approval.');
    }
}
