<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\CommunicationProviderSetting;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use App\Services\Communications\EmailProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $church = Church::factory()->create();
        $user = User::factory()->for($church)->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, PasswordResetNotification::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'password_reset_requested']);
    }

    public function test_password_reset_does_not_reveal_unknown_accounts(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), ['email' => 'missing@example.org']);

        $response->assertSessionHas('status')->assertSessionHasNoErrors();
        Notification::assertNothingSent();
    }

    public function test_password_reset_email_uses_the_church_name(): void
    {
        $church = Church::factory()->create(['name' => 'Grace Community Church']);
        $user = User::factory()->for($church)->create();

        $message = (new PasswordResetNotification('test-token'))->toMail($user);
        $html = (string) $message->render();

        $this->assertStringContainsString('Grace Community Church', $html);
        $this->assertStringNotContainsString('KingdomHub', $html);
    }

    public function test_password_reset_uses_the_church_smtp_provider_when_configured(): void
    {
        Notification::fake();
        $church = Church::factory()->create();
        $user = User::factory()->for($church)->create();

        CommunicationProviderSetting::query()->create([
            'church_id' => $user->church_id,
            'channel' => 'email',
            'provider' => 'SMTP / Mailer',
            'enabled' => true,
            'sender_identity' => 'EcclesiaOS',
            'settings' => [
                'endpoint_url' => 'smtp.example.test',
                'account_id' => 'mailer@example.test',
                'device_id' => 587,
                'api_key_encrypted' => Crypt::encryptString('secret'),
                'sender_number' => 'no-reply@example.test',
            ],
        ]);

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

        $setting = CommunicationProviderSetting::query()->where('church_id', $user->church_id)->firstOrFail();
        $this->assertSame('smtp.example.test', app(EmailProviderManager::class)->transport($setting)['host']);
        Notification::assertSentTo($user, PasswordResetNotification::class);
    }
}
