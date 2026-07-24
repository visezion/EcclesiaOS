<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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
}
