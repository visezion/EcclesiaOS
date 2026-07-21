<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_if($user === null || (! $user->isSuperAdministrator() && ! $user->hasPermission($permission)), 403);

        return $next($request);
    }
}
