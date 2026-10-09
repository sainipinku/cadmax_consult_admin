<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[] ...$guards
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                switch ($guard) {
                    case 'superadmin':
                        return redirect()->route('super.dashboard');
                    case 'admin':
                        return redirect()->route('admin.dashboard');
                    case 'member':
                        $user = Auth::guard('member')->user();
                        if ($user instanceof \App\Models\Member) {
                            $activeRole = session('active_role') ?? ($user->assigned_roles[0] ?? null);
                            if ($activeRole) {
                                $targetUrl = match (strtolower($activeRole)) {
                                    'surveyor', 'survey_man' => route('member.construction.dashboard', ['role' => 'surveyor']),
                                    'site_employee', 'execution' => route('member.construction.execution.index'),
                                    'vehicle_driver', 'driver' => route('member.construction.vehicles.index'),
                                    'draft_person', 'draft_man' => route('member.construction.dashboard', ['role' => 'draft_person']),
                                    'admin', 'project_admin' => route('admin.dashboard'),
                                    default => route('member.construction.dashboard', ['role' => $activeRole]),
                                };
                                return redirect($targetUrl);
                            }
                        }
                        return redirect()->route('member.dashboard');
                    case 'callingteam':
                        return redirect()->route('callingteam.dashboard');
                    default:
                        return redirect('/dashboard');
                }
            }
        }

        return $next($request);
    }
}
