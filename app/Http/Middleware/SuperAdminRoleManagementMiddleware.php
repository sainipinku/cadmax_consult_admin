<?php

namespace App\Http\Middleware;

use App\Models\Member;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminRoleManagementMiddleware
{
    public function __construct(
        protected ConstructionAuthorizationService $authz
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $superAdmin = Auth::guard('superadmin')->user();
        if ($superAdmin instanceof SuperAdmin) {
            $isProductSuperAdmin = (int) $superAdmin->id === 1 || (int) ($superAdmin->is_project_owner ?? 0) === 1;
            $isCompanySuperAdmin = $superAdmin->company_id !== null;

            abort_unless($isProductSuperAdmin || $isCompanySuperAdmin, 403);

            return $next($request);
        }

        $actor = Auth::guard('admin')->user()
            ?? Auth::guard('member')->user()
            ?? Auth::user();

        if (!$actor instanceof Member) {
            abort(403);
        }

        // Scoped company membership is required for non-SuperAdmin role management
        if ($this->authz->resolveCompanyId($actor) === null) {
            abort(403);
        }

        abort_unless(
            $this->authz->hasAnyPermission($actor, [
                'role.view',
                'role.manage',
                'role.create',
                'role.edit',
                'role.delete',
                'role.assign_permissions',
            ]),
            403
        );

        return $next($request);
    }
}
