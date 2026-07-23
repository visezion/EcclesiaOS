<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(ModuleRegistry::isDisabledRoute($request->route()?->getName()), 404);

        return $next($request);
    }
}
