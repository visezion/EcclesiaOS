<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\TotpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AccountSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.settings', [
            'user' => $request->user()->load('church', 'campus', 'roles'),
            'settings' => $this->settings($request->user()->account_settings ?? []),
            'unreadNotifications' => $request->user()->unreadNotifications()->count(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Account Settings', 'url' => null],
            ],
        ]);
    }

    public function update(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $section = (string) $request->input('section');
        $current = $this->settings($user->account_settings ?? []);

        $validated = $request->validate([
            'section' => ['required', Rule::in(['preferences', 'notifications', 'security'])],
            'timezone' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', Rule::in(['en', 'es', 'fr', 'pt'])],
            'date_format' => ['nullable', Rule::in(['M d, Y', 'Y-m-d', 'd M Y', 'm/d/Y'])],
            'theme_mode' => ['nullable', Rule::in(['light', 'dark', 'system'])],
            'default_landing_page' => ['nullable', Rule::in(['dashboard', 'members.index', 'programs.index', 'calendar.index', 'profile.edit'])],
            'compact_tables' => ['nullable', 'boolean'],
            'email_notifications' => ['nullable', 'boolean'],
            'sms_notifications' => ['nullable', 'boolean'],
            'in_app_notifications' => ['nullable', 'boolean'],
            'push_notifications' => ['nullable', 'boolean'],
            'notification_frequency' => ['nullable', Rule::in(['instant', 'daily_digest', 'weekly_summary', 'priority_only'])],
            'notify_security' => ['nullable', 'boolean'],
            'notify_members' => ['nullable', 'boolean'],
            'notify_events' => ['nullable', 'boolean'],
            'notify_reports' => ['nullable', 'boolean'],
            'mfa_enabled' => ['nullable', 'boolean'],
            'mfa_method' => ['nullable', Rule::in(['authenticator', 'email', 'sms'])],
            'login_notifications' => ['nullable', 'boolean'],
            'trusted_device_alerts' => ['nullable', 'boolean'],
            'session_timeout_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'recovery_email' => ['nullable', 'email', 'max:255'],
        ]);

        if ($section === 'preferences') {
            $current['preferences'] = [
                'language' => $validated['language'] ?? $current['preferences']['language'],
                'date_format' => $validated['date_format'] ?? $current['preferences']['date_format'],
                'theme_mode' => $validated['theme_mode'] ?? $current['preferences']['theme_mode'],
                'default_landing_page' => $validated['default_landing_page'] ?? $current['preferences']['default_landing_page'],
                'compact_tables' => $request->boolean('compact_tables'),
            ];

            $user->timezone = $validated['timezone'] ?? $user->timezone;
        }

        if ($section === 'notifications') {
            $current['notifications'] = [
                'email_notifications' => $request->boolean('email_notifications'),
                'sms_notifications' => $request->boolean('sms_notifications'),
                'in_app_notifications' => $request->boolean('in_app_notifications'),
                'push_notifications' => $request->boolean('push_notifications'),
                'notification_frequency' => $validated['notification_frequency'] ?? $current['notifications']['notification_frequency'],
                'notify_security' => $request->boolean('notify_security'),
                'notify_members' => $request->boolean('notify_members'),
                'notify_events' => $request->boolean('notify_events'),
                'notify_reports' => $request->boolean('notify_reports'),
            ];
        }

        if ($section === 'security') {
            $current['security'] = array_replace($current['security'], [
                'mfa_method' => $validated['mfa_method'] ?? $current['security']['mfa_method'],
                'login_notifications' => $request->boolean('login_notifications'),
                'trusted_device_alerts' => $request->boolean('trusted_device_alerts'),
                'session_timeout_minutes' => (int) ($validated['session_timeout_minutes'] ?? $current['security']['session_timeout_minutes']),
            ]);

            $user->mfa_enabled = $request->boolean('mfa_enabled');
            $user->recovery_email = $validated['recovery_email'] ?? null;
        }

        $user->account_settings = $current;
        $user->save();

        $activityLogger->log('Account Settings', 'account_'.$section.'_updated', Str::headline($section).' settings were updated.', $user, ['resource' => 'Account Settings', 'risk' => $section === 'security' ? 'medium' : 'low', 'status' => 'success'], $request);

        return back()->with('status', Str::headline($section).' settings saved.');
    }

    public function testNotification(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'account.settings.test',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => 'Account notification test',
                'message' => 'Your in-app notification preferences are working.',
                'module' => 'Account Settings',
            ], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activityLogger->log('Account Settings', 'test_notification_sent', 'User sent a test account notification.', $user, ['resource' => 'Notifications', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Test notification created.');
    }

    public function mfaSetup(Request $request, TotpService $totpService): View
    {
        $user = $request->user();
        $settings = $this->settings($user->account_settings ?? []);
        $secret = data_get($settings, 'security.mfa_pending_secret_encrypted')
            ? Crypt::decryptString((string) data_get($settings, 'security.mfa_pending_secret_encrypted'))
            : $totpService->generateSecret();

        data_set($settings, 'security.mfa_pending_secret_encrypted', Crypt::encryptString($secret));
        $user->forceFill(['account_settings' => $settings])->save();

        $issuer = config('app.name', 'EcclesiaOS');
        $uri = $totpService->otpauthUri($issuer, $user->email, $secret);

        return view('account.mfa-setup', [
            'user' => $user,
            'secret' => $secret,
            'qrSvg' => $totpService->qrSvg($uri),
            'recoveryCodes' => session('mfa_recovery_codes', []),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Account Settings', 'url' => route('account.settings')],
                ['label' => 'MFA Setup', 'url' => null],
            ],
        ]);
    }

    public function confirmMfa(Request $request, TotpService $totpService, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:16'],
        ]);

        $user = $request->user();
        $settings = $this->settings($user->account_settings ?? []);
        $encrypted = (string) data_get($settings, 'security.mfa_pending_secret_encrypted');

        if ($encrypted === '') {
            return redirect()->route('account.mfa.setup')->withErrors(['code' => 'Generate a setup code first.']);
        }

        $secret = Crypt::decryptString($encrypted);

        if (! $totpService->verify($secret, (string) $validated['code'])) {
            return back()->withErrors(['code' => 'The authenticator code is invalid or expired.']);
        }

        $recoveryCodes = $totpService->generateRecoveryCodes();
        data_set($settings, 'security.mfa_method', 'authenticator');
        data_set($settings, 'security.mfa_confirmed', true);
        data_set($settings, 'security.mfa_secret_encrypted', Crypt::encryptString($secret));
        data_set($settings, 'security.mfa_pending_secret_encrypted', null);
        data_set($settings, 'security.mfa_recovery_code_hashes', collect($recoveryCodes)->map(fn (string $code): string => Hash::make($code))->all());

        $user->forceFill([
            'mfa_enabled' => true,
            'account_settings' => $settings,
        ])->save();

        $activityLogger->log('Account Settings', 'mfa_enabled', 'User enabled authenticator MFA.', $user, ['resource' => 'MFA', 'status' => 'success'], $request);

        return redirect()->route('account.mfa.setup')
            ->with('status', 'Authenticator MFA is enabled. Save your recovery codes now.')
            ->with('mfa_recovery_codes', $recoveryCodes);
    }

    public function regenerateRecoveryCodes(Request $request, TotpService $totpService, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $settings = $this->settings($user->account_settings ?? []);

        abort_unless($user->mfa_enabled && data_get($settings, 'security.mfa_confirmed'), 403);

        $recoveryCodes = $totpService->generateRecoveryCodes();
        data_set($settings, 'security.mfa_recovery_code_hashes', collect($recoveryCodes)->map(fn (string $code): string => Hash::make($code))->all());

        $user->forceFill(['account_settings' => $settings])->save();

        $activityLogger->log('Account Settings', 'mfa_recovery_codes_regenerated', 'User regenerated MFA recovery codes.', $user, ['resource' => 'MFA', 'status' => 'success'], $request);

        return redirect()->route('account.mfa.setup')
            ->with('status', 'New recovery codes generated. The old codes no longer work.')
            ->with('mfa_recovery_codes', $recoveryCodes);
    }

    private function settings(array $settings): array
    {
        return array_replace_recursive([
            'preferences' => [
                'language' => 'en',
                'date_format' => 'M d, Y',
                'theme_mode' => 'system',
                'default_landing_page' => 'dashboard',
                'compact_tables' => false,
            ],
            'notifications' => [
                'email_notifications' => true,
                'sms_notifications' => false,
                'in_app_notifications' => true,
                'push_notifications' => false,
                'notification_frequency' => 'instant',
                'notify_security' => true,
                'notify_members' => true,
                'notify_events' => true,
                'notify_reports' => false,
            ],
            'security' => [
                'mfa_method' => 'authenticator',
                'login_notifications' => true,
                'trusted_device_alerts' => true,
                'session_timeout_minutes' => 60,
            ],
        ], $settings);
    }
}
