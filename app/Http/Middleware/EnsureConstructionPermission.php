<?php

namespace App\Http\Middleware;

use App\Services\Construction\ConstructionAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConstructionPermission
{
    public function __construct(
        private readonly ConstructionAuthorizationService $authorizationService
    ) {
    }

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $actor = $this->authorizationService->resolveActor($request);
        $projectId = $this->authorizationService->inferProjectId($request);

        if (!$this->authorizationService->hasAnyPermission($actor, $permissions, $projectId)) {
            $message = 'Unauthorized: Missing required construction permission.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}
