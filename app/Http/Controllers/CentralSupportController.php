<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CentralSupportSession;
use App\Models\CentralSupportSyncEvent;
use App\Models\Church;
use App\Services\ActivityLogger;
use App\Services\CentralSupportClient;
use App\Services\CentralSupportOutbox;
use App\Services\CentralSupportSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class CentralSupportController extends Controller
{
    public function index(Request $request, CentralSupportSettings $settings): View
    {
        $church = $this->church($request);
        $this->authorizeAccess($request, $church);
        $settings->autoEnroll($church);

        return view('support.connection', [
            'church' => $church,
            'connection' => $settings->forChurch($church),
            'sessions' => CentralSupportSession::query()
                ->where('church_id', $church->id)
                ->with(['approver', 'supportUser', 'ticket'])
                ->latest()
                ->limit(20)
                ->get(),
            'syncStats' => [
                'pending' => CentralSupportSyncEvent::query()->where('church_id', $church->id)->whereIn('status', ['pending', 'failed'])->count(),
                'synced' => CentralSupportSyncEvent::query()->where('church_id', $church->id)->where('status', 'synced')->count(),
                'failed' => CentralSupportSyncEvent::query()->where('church_id', $church->id)->where('status', 'failed')->count(),
            ],
            'grantToken' => session('central_support_grant_token'),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Support Center', 'url' => route('support.index')],
                ['label' => 'Central Connection', 'url' => null],
            ],
        ]);
    }

    public function update(Request $request, CentralSupportSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        $church = $this->church($request);
        $this->authorizeAccess($request, $church);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'remote_access_enabled' => ['required', 'boolean'],
            'api_token' => ['nullable', 'string', 'min:20', 'max:500'],
        ]);

        $settings->save($church, $validated);
        $logger->log('Support', 'central_support_connection_updated', 'Central support connection settings were updated.', $church, [
            'enabled' => (bool) $validated['enabled'],
            'remote_access_enabled' => (bool) $validated['remote_access_enabled'],
        ], $request);

        return back()->with('status', 'Central support connection settings saved.');
    }

    public function test(Request $request, CentralSupportClient $client, CentralSupportSettings $settings): RedirectResponse
    {
        $church = $this->church($request);
        $this->authorizeAccess($request, $church);
        $settings->autoEnroll($church);

        try {
            $message = $client->test($church);
            $settings->recordTest($church, true, $message);

            return back()->with('status', $message);
        } catch (Throwable $exception) {
            report($exception);
            $message = 'The central support server could not be reached or rejected this installation.';
            $settings->recordTest($church, false, $message);

            return back()->withErrors(['connection' => $message]);
        }
    }

    public function sync(Request $request, CentralSupportOutbox $outbox, CentralSupportClient $client): RedirectResponse
    {
        $church = $this->church($request);
        $this->authorizeAccess($request, $church);
        app(CentralSupportSettings::class)->autoEnroll($church);
        $result = $outbox->syncPending($client, $church->id);

        return back()->with(
            $result['failed'] > 0 ? 'error_status' : 'status',
            $result['sent'].' support update(s) synchronized. '.$result['pending'].' remain pending.',
        );
    }

    public function grant(Request $request, CentralSupportSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        $church = $this->church($request);
        $this->authorizeAccess($request, $church);
        $connection = $settings->forChurch($church);
        abort_unless($connection['enabled'] && $connection['remote_access_enabled'] && $connection['api_token_configured'], 422, 'Enable and configure central remote support before creating access.');
        $validated = $request->validate([
            'duration' => ['required', 'integer', 'in:15,30,60,120,240'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $plainToken = Str::random(64);

        $session = CentralSupportSession::query()->create([
            'church_id' => $church->id,
            'approved_by' => $request->user()->id,
            'grant_token_hash' => hash('sha256', $plainToken),
            'scopes' => ['full_support'],
            'status' => 'pending',
            'expires_at' => now()->addMinutes((int) $validated['duration']),
            'user_agent' => $validated['reason'],
        ]);
        $logger->log('Support', 'remote_support_access_granted', 'A temporary central support access grant was created.', $session, [
            'expires_at' => $session->expires_at->toIso8601String(),
            'reason' => $validated['reason'],
            'risk' => 'high',
        ], $request);

        return back()
            ->with('status', 'One-time remote support grant created. Copy it now; it will not be shown again.')
            ->with('central_support_grant_token', $plainToken);
    }

    public function revoke(Request $request, CentralSupportSession $centralSupportSession, ActivityLogger $logger): RedirectResponse
    {
        $church = $this->church($request);
        $this->authorizeAccess($request, $church);
        abort_unless($centralSupportSession->church_id === $church->id, 404);

        DB::transaction(function () use ($centralSupportSession): void {
            $centralSupportSession->update(['status' => 'revoked', 'revoked_at' => now(), 'login_token_hash' => null]);
            if ($centralSupportSession->support_user_id) {
                $centralSupportSession->supportUser()->update(['status' => 'inactive']);
                DB::table('sessions')->where('user_id', $centralSupportSession->support_user_id)->delete();
            }
        });
        $logger->log('Support', 'remote_support_access_revoked', 'Temporary central support access was revoked.', $centralSupportSession, ['risk' => 'high'], $request);

        return back()->with('status', 'Remote support access revoked immediately.');
    }

    private function authorizeAccess(Request $request, Church $church): void
    {
        $user = $request->user();
        abort_unless(
            $user?->isSuperAdministrator()
            || ($user?->church_id === $church->id && $user->hasPermission('manage settings')),
            403,
        );
    }

    private function church(Request $request): Church
    {
        return $request->user()?->church_id
            ? Church::query()->findOrFail($request->user()->church_id)
            : Church::query()->firstOrFail();
    }
}
