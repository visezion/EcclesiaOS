<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\CommunicationMail;
use App\Models\Church;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationProviderSetting;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\CommunicationDeliveryNotification;
use App\Services\Communications\CommunicationDeliveryDispatcher;
use App\Services\Communications\DomainNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class CommunicationReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_in_app_delivery_reaches_the_recipient_account(): void
    {
        Notification::fake();
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id, 'status' => 'active']);
        $delivery = CommunicationDelivery::query()->create([
            'church_id' => $church->id,
            'user_id' => $user->id,
            'channel' => 'in_app',
            'provider' => 'EcclesiaOS',
            'recipient_name' => $user->name,
            'recipient_contact' => $user->email,
            'subject' => 'Assignment ready',
            'body_excerpt' => 'You have a new assignment.',
            'body' => 'You have a new assignment.',
            'event_type' => 'ProgramSectionAssigned',
            'category' => 'volunteers',
            'status' => 'queued',
            'retry_status' => 'queued',
            'attempt' => 1,
        ]);

        $this->artisan('communications:dispatch')->assertSuccessful();

        $this->assertSame('delivered', $delivery->fresh()->status);
        Notification::assertSentTo($user, CommunicationDeliveryNotification::class);
    }

    public function test_due_campaign_is_dispatched_once_by_the_scheduler_command(): void
    {
        Notification::fake();
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id, 'status' => 'active']);
        $campaign = CommunicationCampaign::query()->create([
            'church_id' => $church->id,
            'created_by' => $user->id,
            'name' => 'Due campaign',
            'segment_name' => 'Active users',
            'channels' => ['in_app'],
            'subject' => 'Service reminder',
            'body' => 'Service begins soon.',
            'send_mode' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
            'status' => 'scheduled',
            'recipient_count' => 1,
        ]);
        $campaign->recipients()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'preferences' => ['channels' => ['in_app']],
            'status' => 'scheduled',
        ]);

        $this->artisan('communications:dispatch')->assertSuccessful();
        $this->artisan('communications:dispatch')->assertSuccessful();

        $this->assertSame('sent', $campaign->fresh()->status);
        $this->assertSame(1, $campaign->deliveries()->count());
        Notification::assertSentToTimes($user, CommunicationDeliveryNotification::class, 1);
    }

    public function test_email_delivery_uses_the_configured_laravel_mailer(): void
    {
        Mail::fake();
        $church = Church::factory()->create();
        CommunicationProviderSetting::query()->create([
            'church_id' => $church->id,
            'channel' => 'email',
            'provider' => 'SMTP / Mailer',
            'enabled' => true,
            'rate_limit_per_minute' => 100,
            'retry_policy' => 'exponential',
        ]);
        $delivery = CommunicationDelivery::query()->create([
            'church_id' => $church->id,
            'channel' => 'email',
            'provider' => 'SMTP / Mailer',
            'recipient_name' => 'Test Recipient',
            'recipient_contact' => 'recipient@example.test',
            'subject' => 'Email test',
            'body_excerpt' => 'Email delivery body.',
            'body' => 'Email delivery body.',
            'event_type' => 'ProviderTest',
            'category' => 'system',
            'status' => 'queued',
            'retry_status' => 'queued',
            'attempt' => 1,
        ]);

        app(CommunicationDeliveryDispatcher::class)->dispatch($delivery);

        $this->assertSame('delivered', $delivery->fresh()->status);
        Mail::assertSent(CommunicationMail::class, fn (CommunicationMail $mail): bool => $mail->hasTo('recipient@example.test'));
    }

    public function test_push_delivery_uses_fcm_http_v1_and_records_provider_id(): void
    {
        $church = Church::factory()->create();
        CommunicationProviderSetting::query()->create([
            'church_id' => $church->id,
            'channel' => 'push',
            'provider' => 'Firebase Cloud Messaging',
            'enabled' => true,
            'settings' => [
                'endpoint_url' => 'https://fcm.googleapis.test/v1/projects/ecclesia/messages:send',
                'account_id' => 'ecclesia',
                'api_key_encrypted' => Crypt::encryptString('fcm-access-token'),
            ],
            'rate_limit_per_minute' => 100,
            'retry_policy' => 'exponential',
        ]);
        Http::fake([
            'https://fcm.googleapis.test/v1/projects/ecclesia/messages:send' => Http::response(['name' => 'projects/ecclesia/messages/123'], 200),
        ]);
        $delivery = CommunicationDelivery::query()->create([
            'church_id' => $church->id,
            'channel' => 'push',
            'provider' => 'Firebase Cloud Messaging',
            'recipient_name' => 'Device Recipient',
            'recipient_contact' => 'device-token-123',
            'subject' => 'Push test',
            'body_excerpt' => 'Push delivery body.',
            'body' => 'Push delivery body.',
            'event_type' => 'ProviderTest',
            'category' => 'system',
            'status' => 'queued',
            'retry_status' => 'queued',
            'attempt' => 1,
        ]);

        app(CommunicationDeliveryDispatcher::class)->dispatch($delivery);

        $delivery->refresh();
        $this->assertSame('delivered', $delivery->status);
        $this->assertSame('projects/ecclesia/messages/123', $delivery->provider_message_id);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fcm-access-token')
            && $request['message']['token'] === 'device-token-123');
    }

    public function test_preferences_skip_disabled_channels_and_defer_quiet_hour_digests(): void
    {
        Notification::fake();
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id, 'status' => 'active']);
        UserNotificationPreference::query()->create([
            'church_id' => $church->id,
            'user_id' => $user->id,
            'channels' => ['email'],
            'categories' => ['events'],
            'category_channels' => ['events' => ['email']],
            'digest_mode' => 'daily',
            'quiet_hours_start' => now()->subHour()->format('H:i'),
            'quiet_hours_end' => now()->addHour()->format('H:i'),
            'language' => 'en',
            'critical_alerts' => false,
        ]);

        $service = app(DomainNotificationService::class);
        $skipped = $service->user($user, 'EventSessionCreated', 'events', 'Session created', 'A session was created.', ['in_app'])->first();
        $deferred = $service->user($user, 'EventSessionCreated', 'events', 'Session created', 'A session was created.', ['email'])->first();

        $this->assertSame('skipped', $skipped->status);
        $this->assertSame('queued', $deferred->status);
        $this->assertTrue($deferred->available_at->isFuture());
        Notification::assertNothingSent();
    }

    public function test_explicit_critical_alerts_bypass_quiet_hours_and_digest_delay(): void
    {
        Notification::fake();
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id, 'status' => 'active']);
        UserNotificationPreference::query()->create([
            'church_id' => $church->id,
            'user_id' => $user->id,
            'channels' => ['in_app'],
            'categories' => ['system'],
            'digest_mode' => 'weekly',
            'quiet_hours_start' => now()->subHour()->format('H:i'),
            'quiet_hours_end' => now()->addHour()->format('H:i'),
            'language' => 'en',
            'critical_alerts' => true,
        ]);

        $delivery = app(DomainNotificationService::class)
            ->user($user, 'ApprovalRequested', 'system', 'Approval required', 'Review this request.', ['in_app'], [], true)
            ->first();

        $this->assertSame('delivered', $delivery->status);
        Notification::assertSentTo($user, CommunicationDeliveryNotification::class);
    }
}
