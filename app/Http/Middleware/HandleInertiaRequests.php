<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Services\Construction\ConstructionAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var ConstructionAuthorizationService $constructionAuthorization */
        $constructionAuthorization = app(ConstructionAuthorizationService::class);

        $user = null;
        $guard = null;

        if ($request->session()->has('impersonator')) {
            if (Auth::guard('superadmin')->check()) {
                $user = Auth::guard('superadmin')->user();
                $guard = 'superadmin';
            } elseif (Auth::guard('admin')->check()) {
                $user = Auth::guard('admin')->user();
                $guard = 'admin';
            } elseif (Auth::guard('callingteam')->check()) {
                $user = Auth::guard('callingteam')->user();
                $guard = 'callingteam';
            } elseif (Auth::guard('member')->check()) {
                $user = Auth::guard('member')->user();
                $guard = 'member';
            }
        } else {
            if (Auth::guard('superadmin')->check()) {
                $user = Auth::guard('superadmin')->user();
                $guard = 'superadmin';
            } elseif (Auth::guard('admin')->check()) {
                $user = Auth::guard('admin')->user();
                $guard = 'admin';
            } elseif (Auth::guard('member')->check()) {
                $user = Auth::guard('member')->user();
                $guard = 'member';
            } elseif (Auth::guard('callingteam')->check()) {
                $user = Auth::guard('callingteam')->user();
                $guard = 'callingteam';
            }
        }

        $userAvailableRoles = [];
        $activeRole = null;

        if ($user) {
            if ($user instanceof \App\Models\Member) {
                $slugs = $user->assigned_roles ?? [];
                $names = $user->assigned_role_names ?? [];

                for ($i = 0; $i < count($slugs); $i++) {
                    $userAvailableRoles[] = [
                        'id' => $slugs[$i],
                        'slug' => $slugs[$i],
                        'name' => $names[$i] ?? ucfirst(str_replace('_', ' ', $slugs[$i])),
                    ];
                }

                if (empty($userAvailableRoles)) {
                    $userAvailableRoles[] = ['id' => 'member', 'slug' => 'member', 'name' => 'Member'];
                }

                $sessionRole = $request->session()->get('active_role') ?? $request->query('role');
                if ($sessionRole) {
                    $found = collect($userAvailableRoles)->firstWhere('slug', strtolower($sessionRole));
                    $activeRole = $found ?? $userAvailableRoles[0];
                } else {
                    $activeRole = $userAvailableRoles[0];
                }
            } elseif ($user instanceof \App\Models\SuperAdmin) {
                $slugs = $user->roles ?? [];
                if (is_array($slugs)) {
                    foreach ($slugs as $s) {
                        $cRole = \App\Models\ConstructionRole::where('slug', $s)->orWhere('name', $s)->first();
                        $userAvailableRoles[] = [
                            'id' => $s,
                            'slug' => $s,
                            'name' => $cRole?->name ?? ucfirst(str_replace('_', ' ', $s)),
                        ];
                    }
                }
                if (empty($userAvailableRoles)) {
                    $userAvailableRoles[] = ['id' => 'super_admin', 'slug' => 'super_admin', 'name' => 'Super Admin'];
                }

                $sessionRole = $request->session()->get('active_role') ?? $request->query('role');
                if ($sessionRole) {
                    $found = collect($userAvailableRoles)->firstWhere('slug', strtolower($sessionRole));
                    $activeRole = $found ?? $userAvailableRoles[0];
                } else {
                    $activeRole = $userAvailableRoles[0];
                }
            }

            if ($activeRole && !empty($activeRole['slug'])) {
                session(['active_role' => $activeRole['slug']]);
            }
        }

        $constructionPermissions = $constructionAuthorization->permissionsFor($user);
        $genericPermissions = [];

        $isRestrictedRole = $activeRole && !in_array($activeRole['slug'] ?? '', ['super_admin', 'superadmin', 'admin', 'project_admin']);
        if (!$isRestrictedRole && $user && method_exists($user, 'getAllPermissions')) {
            $genericPermissions = $user->getAllPermissions()->pluck('name')->all();
        }

        $mergedPermissions = collect($genericPermissions)
            ->merge($constructionPermissions)
            ->filter()
            ->unique()
            ->values()
            ->all();

        try {
            \Illuminate\Support\Facades\Log::info('[HandleInertiaRequests Middleware Shared Data]', [
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'guard' => $guard,
                'is_impersonating' => !empty($request->session()->get('impersonator')),
                'construction_permissions_count' => count($constructionPermissions),
                'merged_permissions_count' => count($mergedPermissions),
                'sample_permissions' => array_slice($mergedPermissions, 0, 10),
            ]);
        } catch (\Throwable $e) {
            // Fail-safe
        }

        // Resolve the current project from the route (e.g. project show page)
        // or from the ?project= query parameter.
        // Route model binding resolves the parameter to a Project instance,
        // so handle both model instances and raw IDs.
        $currentProject = null;
        $route = $request->route();
        $routeProjectParam = $route ? $route->parameter('project') : null;

        if ($routeProjectParam instanceof Project) {
            $currentProject = $routeProjectParam->load('client', 'company');
        } elseif ($routeProjectParam) {
            $currentProject = Project::with('client', 'company')
                ->find($routeProjectParam);
        }

        if ($currentProject === null && $request->query('project')) {
            $currentProject = Project::with('client', 'company')
                ->find($request->query('project'));
        }

        $impersonator = $request->session()->get('impersonator');
        $isImpersonating = !empty($impersonator);
        $pendingApprovalCount = ($guard === 'superadmin') ? \App\Models\ActionApprovalRequest::pending()->count() : 0;
        $companyName = $user instanceof \App\Models\SuperAdmin && (int) $user->id !== 1
            ? $user->company?->name
            : null;

        $deleteApprovalRequestsMap = [];

        if ($user) {
            $approvalReqs = \App\Models\ActionApprovalRequest::where('action', 'delete')->get();
            foreach ($approvalReqs as $req) {
                $statusVal = (int) $req->status;
                $resType = strtolower($req->resource_type);
                $resId = (string) $req->resource_id;
                $deleteApprovalRequestsMap["{$resType}_{$resId}"] = [
                    'id' => $req->id,
                    'uuid' => $req->uuid,
                    'status' => $statusVal,
                ];
            }
        }

        return array_merge(parent::share($request), [
            'messages' => flash()->render('array'),
            'delete_approval_requests' => $deleteApprovalRequestsMap,
            'available_roles' => $userAvailableRoles,
            'google_maps_api_key' => (string) (config('services.google.maps_api_key') ?: env('GOOGLE_MAPS_API_KEY') ?: env('VITE_GOOGLE_MAPS_API_KEY') ?: ''),
            'mapbox_token' => (string) (config('services.mapbox.token') ?: env('MAPBOX_TOKEN') ?: env('VITE_MAPBOX_TOKEN') ?: ''),
            'openrouteservice_api_key' => (string) (config('services.openrouteservice.api_key') ?: env('OPENROUTESERVICE_API_KEY') ?: env('VITE_OPENROUTESERVICE_API_KEY') ?: ''),
            'carto_api_key' => (string) (config('services.carto.api_key') ?: env('CARTO_API_KEY') ?: env('VITE_CARTO_API_KEY') ?: ''),

            'auth' => [
                'user' => $user,
                'guard' => $guard,
                'company_name' => $companyName,
                'permissions' => $mergedPermissions,
                'construction_permissions' => $constructionPermissions,
                'is_impersonating' => $isImpersonating,
                'impersonator' => $impersonator,
                'pending_approval_requests_count' => $pendingApprovalCount,
                'available_roles' => $userAvailableRoles,
                'active_role' => $activeRole,
            ],
            'is_impersonating' => $isImpersonating,
            'impersonator' => $impersonator,
            'current_project' => $currentProject
                ? [
                    'id' => $currentProject->id,
                    'name' => $currentProject->name,
                    'project_code' => $currentProject->project_code,
                    'status' => $currentProject->status,
                    'current_stage' => $currentProject->current_stage,
                    'client' => $currentProject->client?->name,
                    'company' => $currentProject->company?->name,
                ]
                : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                // 'error' => $request->session()->get('errors')
                //     ? null
                //     : $request->session()->get('error'),
                // 'warning' => $request->session()->get('warning'),
                // 'info' => $request->session()->get('info'),
            ],

            // 'errors' => $request->session()->get('errors')
            //     ? $request->session()->get('errors')->getBag('default')->getMessages()
            //     : (object)[],
        ]);
    }
}
