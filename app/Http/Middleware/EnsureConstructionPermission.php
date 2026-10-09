<?php

namespace App\Http\Middleware;

use App\Services\Construction\ConstructionAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Permission;

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

        if (!$this->authorizationService->hasAnyPermission(
            $actor,
            $permissions,
            $projectId
        )) {
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
}