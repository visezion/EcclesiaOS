<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_seeded_administrator_can_log_in(): void
    {
        $this->seed();

        $this->post(route('login.store'), [
            'email' => 'admin@kingdomhub.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs(User::query()->where('email', 'admin@kingdomhub.test')->first());
    }

    public function test_login_requires_and_completes_confirmed_mfa(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $user = User::factory()->create([
            'email' => 'mfa@example.org',
            'mfa_enabled' => true,
            'account_settings' => [
                'security' => [
                    'mfa_method' => 'authenticator',
                    'mfa_confirmed' => true,
                    'mfa_secret_encrypted' => Crypt::encryptString($secret),
                    'mfa_recovery_code_hashes' => [],
                ],
            ],
        ]);

        $this->post(route('login.store'), [
            'email' => 'mfa@example.org',
            'password' => 'password',
        ])
            ->assertRedirect(route('login.mfa'))
            ->assertSessionHas('login.mfa_user_id', $user->id);

        $this->assertGuest();

        $this->post(route('login.mfa.verify'), [
            'code' => $totp->code($secret),
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_page_shows_enabled_configured_social_authentication_buttons(): void
    {
        $this->seed();
        $church = Church::query()->firstOrFail();
        $settings = $church->settings ?? [];

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('or continue with', false)
            ->assertDontSee('Google', false);

        data_set($settings, 'social_auth.providers.google', [
            'enabled' => true,
            'client_id' => 'google-client-id',
            'client_secret_encrypted' => Crypt::encryptString('google-secret'),
        ]);
        data_set($settings, 'social_auth.providers.facebook', [
            'enabled' => true,
            'client_id' => 'facebook-client-id',
            'client_secret_encrypted' => null,
        ]);
        data_set($settings, 'social_auth.providers.github', [
            'enabled' => false,
            'client_id' => 'github-client-id',
            'client_secret_encrypted' => Crypt::encryptString('github-secret'),
        ]);
        $church->forceFill(['settings' => $settings])->save();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('or continue with', false)
            ->assertSee('Google', false)
            ->assertSee(route('social.redirect', 'google'), false)
            ->assertDontSee('Facebook', false)
            ->assertDontSee('GitHub', false);
    }

    public function test_enabled_google_social_login_matches_existing_active_user(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $church = Church::query()->firstOrFail();
        $settings = $church->settings ?? [];
        data_set($settings, 'social_auth.providers.google', [
            'enabled' => true,
            'client_id' => 'google-client-id',
            'client_secret_encrypted' => Crypt::encryptString('google-secret'),
        ]);
        $church->forceFill(['settings' => $settings])->save();

        $redirect = $this->get(route('social.redirect', 'google'));
        $redirect->assertRedirect();
        $this->assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $redirect->headers->get('Location'));

        $state = session('social_auth.google.state');
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-value', 'token_type' => 'Bearer'], 200),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google-user-123',
                'email' => 'admin@kingdomhub.test',
                'name' => 'Pastor John',
                'picture' => 'https://example.test/avatar.png',
            ], 200),
        ]);

        $this->get(route('social.callback', 'google').'?state='.$state.'&code=auth-code')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
            'email' => 'admin@kingdomhub.test',
        ]);
        $this->assertNotNull(SocialAccount::query()->where('provider', 'google')->first()?->last_login_at);
    }
}
