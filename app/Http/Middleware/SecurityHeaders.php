<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=()');
        $scriptSources = ["'self'", "'unsafe-inline'", "'unsafe-eval'"];

        if (filter_var(config('services.cloudflare_insights.enabled', false), FILTER_VALIDATE_BOOL)) {
            $scriptSources[] = 'https://static.cloudflareinsights.com';
        }

        $formSources = ["'self'"];
        $applicationUrl = config('app.url');

        if (is_string($applicationUrl) && Str::startsWith($applicationUrl, 'https://')) {
            $formSources[] = rtrim($applicationUrl, '/');
        }

        $response->headers->set(
            'Content-Security-Policy',
            implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "frame-ancestors 'self'",
                'form-action '.implode(' ', $formSources),
                "img-src 'self' data: blob: https:",
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
                'script-src '.implode(' ', $scriptSources),
                "connect-src 'self' https: wss:",
                "font-src 'self' data: https://fonts.bunny.net",
                "media-src 'self' blob: https:",
            ]).';'
        );

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->user() || $request->is('login*', 'forgot-password', 'reset-password*', 'auth/*', 'account/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
