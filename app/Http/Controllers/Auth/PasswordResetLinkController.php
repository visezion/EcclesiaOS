<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

final class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        $activityLogger->log('Authentication', 'password_reset_requested', 'Password reset link requested.', properties: [
            'email' => $request->string('email')->toString(),
            'status' => $status,
        ], request: $request);

        // Keep the response generic so account existence cannot be enumerated, but do
        // not claim delivery when the configured mail transport rejected the request.
        return in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)
            ? back()->with('status', __(Password::RESET_LINK_SENT))
            : back()->withErrors(['email' => __($status)]);
    }
}
