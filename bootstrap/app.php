<?php

use App\Http\Middleware\ContractorMiddleware;
use App\Http\Middleware\EnsureConstructionPermission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PartnerMiddleware;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SuperAdminMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
        '*',
    ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'auth.superadmin' => SuperAdminMiddleware::class,
            'authorized' => RedirectIfAuthenticated::class,
             'member' => \App\Http\Middleware\MemberMiddleware::class,
              'admin' => \App\Http\Middleware\AdminMiddleware::class,
              'callingteam' => \App\Http\Middleware\CallingTeamMiddleware::class,
              'construction.permission' => EnsureConstructionPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
