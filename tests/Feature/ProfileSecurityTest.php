<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_renders(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->assertTrue(Storage::disk('public')->exists($user->avatar_url));

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('User Profile')
            ->assertSee('Profile Completion')
            ->assertSee('Edit Profile')
            ->assertSee($user->avatar_src, false);
    }

    public function test_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Updated Person',
                'title' => 'Coordinator',
                'email' => $user->email,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Person']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'profile_updated']);
    }

    public function test_password_can_be_changed_from_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'password',
                'password' => 'NewPassword!234',
                'password_confirmation' => 'NewPassword!234',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('NewPassword!234', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'password_changed']);
    }

    public function test_profile_avatar_can_be_uploaded(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'date_of_birth' => '1978-03-12',
                'gender' => 'Male',
                'avatar' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertNotNull($user->avatar_url);
        $this->assertStringStartsWith('avatars/', $user->avatar_url);
        $this->assertStringContainsString('/storage/avatars/', $user->avatar_src);
        $this->assertSame('1978-03-12', $user->date_of_birth?->toDateString());
        Storage::disk('public')->assertExists($user->avatar_url);
    }

    public function test_profile_impersonation_preview_button_sets_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.impersonate'))
            ->assertRedirect()
            ->assertSessionHas('profile_preview_user_id', $user->id);

        $this->assertDatabaseHas('activity_logs', ['action' => 'profile_preview_started']);
    }

    public function test_account_settings_preferences_notifications_and_security_are_persistent(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC', 'mfa_enabled' => false]);

        $this->actingAs($user)
            ->get(route('account.settings'))
            ->assertOk()
            ->assertSee('Account Settings')
            ->assertSee('Notification Preferences')
            ->assertSee('Security & MFA', false);

        $this->actingAs($user)
            ->put(route('account.settings.update'), [
                'section' => 'preferences',
                'timezone' => 'Asia/Nicosia',
                'language' => 'en',
                'date_format' => 'Y-m-d',
                'theme_mode' => 'dark',
                'default_landing_page' => 'programs.index',
                'compact_tables' => '1',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('Asia/Nicosia', $user->timezone);
        $this->assertSame('dark', $user->account_settings['preferences']['theme_mode']);
        $this->assertTrue($user->account_settings['preferences']['compact_tables']);

        $this->actingAs($user)
            ->get(route('account.settings'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false);

        $this->actingAs($user)
            ->put(route('account.settings.update'), [
                'section' => 'notifications',
                'email_notifications' => '1',
                'in_app_notifications' => '1',
                'notification_frequency' => 'daily_digest',
                'notify_security' => '1',
                'notify_events' => '1',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('daily_digest', $user->account_settings['notifications']['notification_frequency']);
        $this->assertTrue($user->account_settings['notifications']['email_notifications']);
        $this->assertFalse($user->account_settings['notifications']['sms_notifications']);

        $this->actingAs($user)
            ->put(route('account.settings.update'), [
                'section' => 'security',
                'mfa_enabled' => '1',
                'mfa_method' => 'email',
                'login_notifications' => '1',
                'trusted_device_alerts' => '1',
                'session_timeout_minutes' => 120,
                'recovery_email' => 'recovery@example.org',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->mfa_enabled);
        $this->assertSame('recovery@example.org', $user->recovery_email);
        $this->assertSame('email', $user->account_settings['security']['mfa_method']);
        $this->assertSame(120, $user->account_settings['security']['session_timeout_minutes']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'account_security_updated']);
    }

    public function test_account_settings_can_create_a_real_test_notification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('account.settings.test-notification'))
            ->assertRedirect();

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'test_notification_sent']);
    }
}
