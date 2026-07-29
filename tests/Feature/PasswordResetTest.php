<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'password_reset_requested']);
    }

    public function test_password_reset_does_not_reveal_unknown_accounts(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), ['email' => 'missing@example.org']);

        $response->assertSessionHas('status')->assertSessionHasNoErrors();
        Notification::assertNothingSent();
    }
}
