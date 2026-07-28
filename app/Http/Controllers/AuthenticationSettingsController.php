<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Church;
use App\Services\ActivityLogger;
use App\Support\SocialAuthProviderRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

final class AuthenticationSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSettings($request);

        $church = $this->settingsChurch();
        $settings = $church->settings ?? [];
        $providers = $this->providersForView($settings);

        return view('admin.authentication', [
            'church' => $church,
            'providers' => $providers,
            'stats' => [
                'enabled' => $providers->where('enabled', true)->count(),
                'configured' => $providers->where('configured', true)->count(),
                'available' => $providers->count(),
                'recent_social_logins' => ActivityLog::query()
                    ->where('module', 'Authentication')
                    ->where('action', 'social_login')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
            ],
            'recentActivity' => ActivityLog::query()
                ->with('user')
                ->where('module', 'Authentication')
                ->latest()
                ->limit(8)
                ->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Administration', 'url' => route('users.index')],
                ['label' => 'Authentication', 'url' => null],
            ],
        ]);
    }

    public function update(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeSettings($request);

        $providerKeys = SocialAuthProviderRegistry::providers()->keys()->all();
        $validated = $request->validate([
            'providers' => ['nullable', 'array'],
            'providers.*.provider' => ['required', Rule::in($providerKeys)],
            'providers.*.enabled' => ['nullable', 'boolean'],
            'providers.*.client_id' => ['nullable', 'string', 'max:500'],
            'providers.*.client_secret' => ['nullable', 'string', 'max:1000'],
            'providers.*.clear_secret' => ['nullable', 'boolean'],
        ]);

        $church = $this->settingsChurch();
        $settings = $church->settings ?? [];
        $existing = data_get($settings, 'social_auth.providers', []);
        $nextProviders = [];

        foreach (SocialAuthProviderRegistry::providers() as $key => $definition) {
            $row = collect($validated['providers'] ?? [])->firstWhere('provider', $key) ?? [];
            $old = $existing[$key] ?? [];
            $secret = $old['client_secret_encrypted'] ?? null;

            if (! empty($row['clear_secret'])) {
                $secret = null;
            }

            if (filled($row['client_secret'] ?? null)) {
                $secret = Crypt::encryptString((string) $row['client_secret']);
            }

            $nextProviders[$key] = [
                'enabled' => (bool) ($row['enabled'] ?? false),
                'client_id' => filled($row['client_id'] ?? null) ? (string) $row['client_id'] : null,
                'client_secret_encrypted' => $secret,
                'label' => $definition['label'],
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        data_set($settings, 'social_auth.providers', $nextProviders);
        data_set($settings, 'social_auth.require_existing_user', true);
        data_set($settings, 'social_auth.last_updated_by', $request->user()?->name);
        data_set($settings, 'social_auth.last_updated_at', now()->toDateTimeString());

        $church->forceFill(['settings' => $settings])->save();

        $activityLogger->log('Settings', 'authentication_settings_updated', 'Administrator updated social authentication providers.', $church, [
            'resource' => 'Authentication Settings',
            'risk' => 'medium',
            'status' => 'success',
            'enabled_providers' => collect($nextProviders)->filter(fn (array $provider): bool => $provider['enabled'])->keys()->values()->all(),
        ], $request);

        return back()->with('status', 'Authentication providers saved.');
    }

    private function authorizeSettings(Request $request): void
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdministrator() || $user?->hasPermission('manage settings'), 403);
    }

    private function settingsChurch(): Church
    {
        return Church::query()->firstOrCreate(
            ['slug' => 'kingdom-life-global-church'],
            [
                'name' => config('church.name'),
                'timezone' => config('church.timezone'),
                'currency' => config('church.currency'),
                'email' => config('church.contact_email'),
                'phone' => config('church.contact_phone'),
                'address' => config('church.address'),
                'settings' => [],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function providersForView(array $settings)
    {
        $configured = data_get($settings, 'social_auth.providers', []);

        return SocialAuthProviderRegistry::providers()
            ->map(function (array $definition, string $key) use ($configured): array {
                $provider = $configured[$key] ?? [];

                return [
                    ...$definition,
                    'key' => $key,
                    'enabled' => (bool) ($provider['enabled'] ?? false),
                    'client_id' => (string) ($provider['client_id'] ?? ''),
                    'configured' => filled($provider['client_id'] ?? null) && filled($provider['client_secret_encrypted'] ?? null),
                    'has_secret' => filled($provider['client_secret_encrypted'] ?? null),
                    'callback_url' => route('social.callback', $key),
                ];
            })
            ->values();
    }
}
