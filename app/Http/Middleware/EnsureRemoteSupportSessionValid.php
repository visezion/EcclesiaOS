<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\CentralSupportSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRemoteSupportSessionValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! data_get($user?->account_settings, 'remote_support.managed')) {
            return $next($request);
        }

        $sessionId = $request->session()->get('remote_support_session_id');
        $session = $sessionId ? CentralSupportSession::query()->find($sessionId) : null;
        if (! $session || $session->support_user_id !== $user->id || $session->status !== 'active' || ! $session->isUsable()) {
            $session?->update(['status' => $session->revoked_at ? 'revoked' : 'expired']);
            $user->update(['status' => 'inactive']);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'The temporary remote support session has expired or was revoked.']);
        }

        if ($session->last_seen_at === null || $session->last_seen_at->lt(now()->subMinutes(2))) {
            $session->update(['last_seen_at' => now(), 'ip_address' => $request->ip(), 'user_agent' => Str::limit((string) $request->userAgent(), 1000, '')]);
        }

        return $next($request);
    }
}
