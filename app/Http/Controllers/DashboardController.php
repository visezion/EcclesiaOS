<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboardService): View
    {
        return view('dashboard.index', $dashboardService->getDashboardData());
    }
}
