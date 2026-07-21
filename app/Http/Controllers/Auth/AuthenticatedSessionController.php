<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $request->user()?->forceFill(['last_login_at' => now()])->save();
        $activityLogger->log('Authentication', 'login', 'User signed in.', $request->user(), request: $request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $activityLogger->log('Authentication', 'logout', 'User signed out.', $request->user(), request: $request);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
