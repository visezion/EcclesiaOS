<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
