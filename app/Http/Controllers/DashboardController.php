<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboardService): View
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('view dashboard'), 403);

        return view('dashboard.index', $dashboardService->forUser($request->user())->getDashboardData());
    }
}
