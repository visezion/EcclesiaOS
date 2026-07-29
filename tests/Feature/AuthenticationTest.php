<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\TotpService;
use App\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_page_is_available_to_guests(): void
    {
        $this->seed();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Empowering Churches.', false)
            ->assertSee('Transforming Lives.', false)
            ->assertSee(route('features'), false)
            ->assertSee(config('church.download_url'), false)
            ->assertSee(config('church.documentation_url'), false)
            ->assertDontSee('Pricing', false)
            ->assertSee(route('login'), false);
    }

    public function test_public_features_page_is_available_to_guests(): void
    {
        $this->seed();

        $response = $this->get(route('features'));

        $response->assertOk()
            ->assertSee('Powerful Features.', false)
            ->assertSee('Features Built for Every Ministry Need', false)
            ->assertSee('Members', false)
            ->assertSee('Book Store', false)
            ->assertSee('Delivery Logs', false)
            ->assertSee('Reports &amp; Analytics', false)
            ->assertSee('Authentication', false)
            ->assertSee('Studio', false)
            ->assertSee(asset('images/landing-dashboard.png'), false)
            ->assertSee(asset('images/landing-dashboard.webp'), false)
            ->assertSee(route('login'), false);

        $this->assertSame(
            ModuleRegistry::modules()->count(),
            substr_count($response->getContent(), 'x-show="featureFilter'),
        );
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_security_headers_and_sensitive_route_throttles_are_enabled(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertContains('throttle:6,1', Route::getRoutes()->getByName('login.mfa.verify')->gatherMiddleware());
        $this->assertContains('throttle:6,1', Route::getRoutes()->getByName('password.email')->gatherMiddleware());
        $this->assertContains('throttle:20,1', Route::getRoutes()->getByName('meetings.rooms.short.qna.store')->gatherMiddleware());
        $this->assertContains('throttle:120,1', Route::getRoutes()->getByName('meeting-attendance.webhook')->gatherMiddleware());
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

    public function test_login_page_uses_branding_and_appearance_settings(): void
    {
        $this->seed();
        $church = Church::query()->firstOrFail();
        $settings = array_merge($church->settings ?? [], [
            'system_name' => 'Custom Ministry OS',
            'church_name' => 'Custom Church',
            'subtitle' => 'Custom ministry platform',
            'logo' => 'branding/custom-logo.png',
            'favicon' => 'branding/custom-favicon.png',
            'sidebar_background' => 'branding/custom-sidebar.png',
            'primary_color' => '#123456',
            'secondary_color' => '#ABCDEF',
            'page_background' => '#F1F2F3',
            'card_radius' => 16,
            'font_family' => 'Lato',
            'font_scale' => 'comfortable',
            'theme_mode' => 'dark',
            'sidebar_start_color' => '#101112',
            'sidebar_middle_color' => '#202122',
            'sidebar_end_color' => '#303132',
            'sidebar_text_color' => '#E1E2E3',
            'sidebar_profile_color' => '#404142',
        ]);
        $church->forceFill(['settings' => $settings])->save();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Custom Ministry OS', false)
            ->assertSee('Custom ministry platform', false)
            ->assertSee('data-theme="dark"', false)
            ->assertSee('--brand-primary: #123456', false)
            ->assertSee('--brand-secondary: #ABCDEF', false)
            ->assertSee('--page-bg: #F1F2F3', false)
            ->assertSee('--card-radius: 16px', false)
            ->assertSee('--font-app: Lato, ui-sans-serif, system-ui, sans-serif', false)
            ->assertSee('--app-font-size: 0.9375rem', false)
            ->assertSee('--sidebar-start: #101112', false)
            ->assertSee('--sidebar-mid: #202122', false)
            ->assertSee('--sidebar-end: #303132', false)
            ->assertSee('--sidebar-text: #E1E2E3', false)
            ->assertSee('--sidebar-profile-bg: #404142', false)
            ->assertSee(asset('storage/branding/custom-logo.png'), false)
            ->assertSee(asset('storage/branding/custom-favicon.png'), false)
            ->assertSee(asset('storage/branding/custom-sidebar.png'), false);
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
