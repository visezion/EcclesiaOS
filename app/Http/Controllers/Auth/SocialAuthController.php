<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\SocialAuthProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class SocialAuthController extends Controller
{
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $definition = SocialAuthProviderRegistry::provider($provider);
        $settings = $this->providerSettings($provider);

        if (! $this->providerIsReady($settings)) {
            return redirect()->route('login')->withErrors(['email' => $definition['label'].' sign-in is not configured.']);
        }

        $state = Str::random(48);
        $request->session()->put("social_auth.{$provider}.state", $state);

        $parameters = [
            'client_id' => $settings['client_id'],
            'redirect_uri' => route('social.callback', $provider),
            'response_type' => 'code',
            'scope' => $definition['scope'],
            'state' => $state,
        ];

        if (($definition['pkce'] ?? false) === true) {
            $verifier = $this->pkceVerifier();
            $request->session()->put("social_auth.{$provider}.code_verifier", $verifier);
            $parameters['code_challenge'] = $this->pkceChallenge($verifier);
            $parameters['code_challenge_method'] = 'S256';
        }

        return redirect()->away($definition['auth_url'].'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(Request $request, string $provider, ActivityLogger $activityLogger): RedirectResponse
    {
        $definition = SocialAuthProviderRegistry::provider($provider);
        $settings = $this->providerSettings($provider);

        if (! $this->providerIsReady($settings)) {
            return redirect()->route('login')->withErrors(['email' => $definition['label'].' sign-in is not configured.']);
        }

        if ($request->filled('error')) {
            return redirect()->route('login')->withErrors(['email' => $request->string('error_description')->toString() ?: 'Social sign-in was cancelled.']);
        }

        $expectedState = $request->session()->pull("social_auth.{$provider}.state");
        if (! is_string($expectedState) || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return redirect()->route('login')->withErrors(['email' => 'Social sign-in session expired. Please try again.']);
        }

        $token = $this->exchangeCodeForToken($request, $provider, $definition, $settings);
        if ($token === null) {
            return redirect()->route('login')->withErrors(['email' => 'Could not confirm '.$definition['label'].' sign-in. Check the provider credentials.']);
        }

        $profile = $this->fetchProfile($provider, $definition, $token);
        if ($profile === null || blank($profile['id'] ?? null)) {
            return redirect()->route('login')->withErrors(['email' => 'Could not read your '.$definition['label'].' account profile.']);
        }

        $socialAccount = SocialAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', (string) $profile['id'])
            ->first();

        $user = null;
        if ($socialAccount instanceof SocialAccount && $socialAccount->user instanceof User) {
            $user = $socialAccount->user;
        }

        if (! $user && filled($profile['email'] ?? null)) {
            $matchedUser = User::query()->whereRaw('LOWER(email) = ?', [Str::lower((string) $profile['email'])])->first();
            if ($matchedUser instanceof User) {
                $user = $matchedUser;
            }
        }

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'No active account is linked to this '.$definition['label'].' identity. Sign in with email first or ask an administrator to create your user account.']);
        }

        if ($user->status !== 'active') {
            return redirect()->route('login')->withErrors(['email' => 'This account is not active. Contact an administrator.']);
        }

        SocialAccount::query()->updateOrCreate(
            ['provider' => $provider, 'provider_user_id' => (string) $profile['id']],
            [
                'user_id' => $user->id,
                'email' => $profile['email'] ?? null,
                'name' => $profile['name'] ?? null,
                'avatar_url' => $profile['avatar_url'] ?? null,
                'raw_profile' => $profile['raw'] ?? [],
                'last_login_at' => now(),
            ],
        );

        Auth::login($user);

        if ($user->mfa_enabled && data_get($user->account_settings, 'security.mfa_confirmed') && filled(data_get($user->account_settings, 'security.mfa_secret_encrypted'))) {
            $request->session()->put('login.mfa_user_id', $user->id);
            $request->session()->put('login.remember', false);
            Auth::guard('web')->logout();

            return redirect()->route('login.mfa')->with('status', $definition['label'].' confirmed. Complete multi-factor authentication to continue.');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $activityLogger->log('Authentication', 'social_login', 'User signed in with '.$definition['label'].'.', $user, ['provider' => $provider, 'status' => 'success'], $request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>|null
     */
    private function exchangeCodeForToken(Request $request, string $provider, array $definition, array $settings): ?array
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'redirect_uri' => route('social.callback', $provider),
            'code' => $request->query('code'),
        ];

        if (($definition['pkce'] ?? false) === true) {
            $payload['code_verifier'] = $request->session()->pull("social_auth.{$provider}.code_verifier");
        }

        $requestBuilder = Http::asForm()->acceptJson();
        if ($provider === 'x' && filled($settings['client_secret'] ?? null)) {
            $requestBuilder = $requestBuilder->withBasicAuth((string) $settings['client_id'], (string) $settings['client_secret']);
        }

        $response = $requestBuilder->post((string) $definition['token_url'], $payload);

        return $response->successful() ? $response->json() : null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>|null
     */
    private function fetchProfile(string $provider, array $definition, array $token): ?array
    {
        $accessToken = (string) ($token['access_token'] ?? '');
        if ($accessToken === '') {
            return null;
        }

        $response = Http::withToken($accessToken)->acceptJson()->get((string) $definition['user_url']);
        if (! $response->successful()) {
            return null;
        }

        $raw = $response->json();

        if ($provider === 'github' && blank($raw['email'] ?? null) && filled($definition['emails_url'] ?? null)) {
            $emails = Http::withToken($accessToken)->acceptJson()->get((string) $definition['emails_url']);
            if ($emails->successful()) {
                $primary = collect($emails->json())->first(fn (array $email): bool => ($email['primary'] ?? false) === true && ($email['verified'] ?? false) === true);
                $raw['email'] = $primary['email'] ?? null;
            }
        }

        return match ($provider) {
            'google', 'linkedin', 'microsoft' => [
                'id' => $raw['sub'] ?? null,
                'email' => $raw['email'] ?? null,
                'name' => $raw['name'] ?? null,
                'avatar_url' => $raw['picture'] ?? null,
                'raw' => $raw,
            ],
            'facebook' => [
                'id' => $raw['id'] ?? null,
                'email' => $raw['email'] ?? null,
                'name' => $raw['name'] ?? null,
                'avatar_url' => data_get($raw, 'picture.data.url'),
                'raw' => $raw,
            ],
            'github' => [
                'id' => $raw['id'] ?? null,
                'email' => $raw['email'] ?? null,
                'name' => $raw['name'] ?? $raw['login'] ?? null,
                'avatar_url' => $raw['avatar_url'] ?? null,
                'raw' => $raw,
            ],
            'x' => [
                'id' => data_get($raw, 'data.id'),
                'email' => null,
                'name' => data_get($raw, 'data.name') ?? data_get($raw, 'data.username'),
                'avatar_url' => data_get($raw, 'data.profile_image_url'),
                'raw' => $raw,
            ],
            default => null,
        };
    }

    /**
     * @return array{enabled: bool, client_id: ?string, client_secret: ?string}
     */
    private function providerSettings(string $provider): array
    {
        $settings = data_get(Church::query()->first()?->settings, "social_auth.providers.{$provider}", []);
        $secret = null;

        if (filled($settings['client_secret_encrypted'] ?? null)) {
            try {
                $secret = Crypt::decryptString((string) $settings['client_secret_encrypted']);
            } catch (\Throwable) {
                $secret = null;
            }
        }

        return [
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'client_id' => filled($settings['client_id'] ?? null) ? (string) $settings['client_id'] : null,
            'client_secret' => $secret,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function providerIsReady(array $settings): bool
    {
        return ($settings['enabled'] ?? false) === true
            && filled($settings['client_id'] ?? null)
            && filled($settings['client_secret'] ?? null);
    }

    private function pkceVerifier(): string
    {
        return Str::random(96);
    }

    private function pkceChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
