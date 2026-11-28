<?php

namespace App\Http\Middleware;

use App\Models\DraftingJob;
use App\Models\DrawingApproval;
use App\Models\Permission;
use App\Models\Project;
use App\Services\Construction\CadApprovalAssignmentService;
use App\Services\Construction\ConstructionAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureConstructionPermission
{
    public function __construct(
        private readonly ConstructionAuthorizationService $authorizationService
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string ...$permissions
    ): Response {
        $actor = $this->authorizationService->resolveActor($request);
        $projectId = $this->authorizationService->inferProjectId($request);

        if ($this->authorizationService->isProjectOwner($actor)) {
            return $next($request);
        }

        // Any Super Admin actor accessing super admin/admin routes has full permission access
        if ($actor instanceof \App\Models\SuperAdmin) {
            $activeRoleSlug = session('active_role') ?? $request->query('role');
            $isRestricted = $activeRoleSlug && !in_array(strtolower(trim($activeRoleSlug)), ['super_admin', 'superadmin', 'crm_super_admin', 'project_owner', 'admin', 'project_admin'], true);
            if (!$isRestricted) {
                return $next($request);
            }
        }

        if ($this->authorizationService->isProjectOwnerOrSuperAdminEmulating($actor)) {
            if ($request->isMethodSafe()) {
                return $next($request);
            }
        }

        $flatPermissions = [];
        foreach ($permissions as $p) {
            foreach (explode(',', $p) as $sub) {
                $sub = trim($sub);
                if ($sub !== '') {
                    $flatPermissions[] = $sub;
                }
            }
        }
        $permissions = !empty($flatPermissions) ? $flatPermissions : $permissions;

        if (!$this->authorizationService->hasAnyPermission(
            $actor,
            $permissions,
            $projectId
        )) {
            if ($this->isAssignedCadApprover($request, $actor, $permissions)) {
                return $next($request);
            }

            $requiredPermission = $permissions[0] ?? 'access.restricted';
            $message = "Unauthorized: You do not have permission ({$requiredPermission}) to access this page.";

            if ($request->expectsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'required_permission' => $requiredPermission,
                ], 403);
            }

            $permissionRecord = Permission::where('slug', $requiredPermission)->first();
            $permissionName = $permissionRecord?->name ?? ucfirst(str_replace(['.', '_'], ' ', $requiredPermission));
            $moduleName = $permissionRecord?->module ?? 'Construction ERP';

            return Inertia::render('Errors/PermissionDenied', [
                'required_permission' => $requiredPermission,
                'permission_name' => $permissionName,
                'module_name' => $moduleName,
                'requested_url' => $request->fullUrl(),
                'message' => $message,
            ])->toResponse($request)->setStatusCode(403);
        }

        return $next($request);
    }

    /**
     * Allow an Admin/Member who was explicitly assigned to a pending CAD approval
     * to access that approval review endpoint even if their role lacks generic approval permissions.
     */
    private function isAssignedCadApprover(Request $request, mixed $actor, array $permissions): bool
    {
        if (! $actor || ! (in_array('drawing_approval.approve', $permissions, true) || in_array('drawing_approval.manage', $permissions, true))) {
            return false;
        }

        $routeProject = $request->route('project');
        if (is_numeric($routeProject)) {
            $routeProject = Project::find((int) $routeProject);
        }

        $routeDraftingJob = $request->route('draftingJob');
        if (is_numeric($routeDraftingJob)) {
            $routeDraftingJob = DraftingJob::find((int) $routeDraftingJob);
        }

        $routeDrawingApproval = $request->route('drawingApproval');
        if (is_numeric($routeDrawingApproval)) {
            $routeDrawingApproval = DrawingApproval::find((int) $routeDrawingApproval);
        }

        return app(CadApprovalAssignmentService::class)->canActorReviewAssignedApproval(
            $actor,
            $routeProject instanceof Project ? $routeProject : null,
            $routeDraftingJob instanceof DraftingJob ? $routeDraftingJob : null,
            $routeDrawingApproval instanceof DrawingApproval ? $routeDrawingApproval : null
        );
    }
}