<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Branding;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $landingPageEnabled = (bool) data_get(Branding::current()->settings, 'admin_landing_page_enabled', true);

        return $landingPageEnabled
            ? view('landing')
            : redirect()->route('login');
    }
}
