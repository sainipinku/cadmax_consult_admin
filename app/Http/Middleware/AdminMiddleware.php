<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
   // AdminMiddleware.php
    public function handle(Request $request, Closure $next)
    {
        if (
            Auth::guard('admin')->check() ||
            Auth::guard('superadmin')->check() ||
            session()->has('impersonator')
        ) {
            return $next($request);
        }

        return redirect()->route('login')->with('error', 'Please login as admin');
    }

}
