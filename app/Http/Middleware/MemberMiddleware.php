<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Member;
class MemberMiddleware
{

    public function handle(Request $request, Closure $next)
    {
        if (
            Auth::guard('member')->check() ||
            Auth::guard('superadmin')->check() ||
            Auth::guard('admin')->check() ||
            Auth::guard('callingteam')->check() ||
            Auth::guard('web')->check() ||
            session()->has('impersonator')
        ) {
            return $next($request);
        }

        return redirect()->route('login')->with('error', 'Please login to continue');
    }
}
