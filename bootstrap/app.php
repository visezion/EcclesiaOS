<?php

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureRemoteSupportSessionValid;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Set TRUSTED_PROXIES to the reverse proxy/Cloudflare addresses in production.
        // This allows Laravel to correctly detect the original HTTPS request.
        $middleware->trustProxies(env('TRUSTED_PROXIES'));
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [EnsureRemoteSupportSessionValid::class]);
        $middleware->validateCsrfTokens(except: ['webhooks/stripe', 'webhooks/payments/*']);

        $middleware->alias([
            'module.enabled' => EnsureModuleEnabled::class,
            'permission' => EnsureUserHasPermission::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
