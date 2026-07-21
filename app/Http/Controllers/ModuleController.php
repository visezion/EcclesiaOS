<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ModuleController extends Controller
{
    public function __invoke(Request $request): View
    {
        $routeName = (string) $request->route()?->getName();
        $module = collect(config('navigation'))->firstWhere('route', $routeName) ?? $this->profileModule($routeName);

        abort_if($module === null, 404);

        return view('modules.coming-soon', [
            'module' => $module,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => $module['label'], 'url' => null],
            ],
        ]);
    }

    private function profileModule(string $routeName): ?array
    {
        return match ($routeName) {
            'profile.edit' => [
                'label' => 'Profile',
                'icon' => 'user-round',
                'planned' => ['Profile details', 'Password updates', 'Notification preferences', 'Connected devices'],
            ],
            'account.settings' => [
                'label' => 'Account Settings',
                'icon' => 'user-round-cog',
                'planned' => ['Account preferences', 'Security settings', 'Session management', 'Personal defaults'],
            ],
            default => null,
        };
    }
}
