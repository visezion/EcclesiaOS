<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Church;
use App\Models\CommunicationDelivery;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\NotificationAutomationRule;
use App\Models\Role;
use App\Models\User;
use App\Services\Communications\DomainNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class NotificationAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_and_configure_the_automation_dashboard(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->get(route('communications.automation'))
            ->assertOk()
            ->assertSee('Notification Automation')
            ->assertSee('Upcoming event reminder')
            ->assertSee('Retry failed');

        $this->assertDatabaseCount('notification_automation_rules', 17);
        $rule = NotificationAutomationRule::query()->where('church_id', $admin->church_id)->where('event_type', 'EventCreated')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('communications.automation.update', $rule), [
                'name' => 'New event alert',
                'enabled' => '1',
                'category' => 'events',
                'channels' => ['in_app', 'email'],
                'audience' => 'all_users',
                'critical' => '1',
            ])
            ->assertRedirect();

        $rule->refresh();
        $this->assertSame('New event alert', $rule->name);
        $this->assertSame(['in_app', 'email'], $rule->channels);
        $this->assertSame('all_users', $rule->audience);
        $this->assertTrue($rule->critical);
    }

    public function test_disabled_rule_stops_real_event_delivery(): void
    {
        Notification::fake();
        $user = $this->administrator();
        NotificationAutomationRule::query()->create([
            'church_id' => $user->church_id,
            'event_type' => 'PaymentStatusChanged',
            'name' => 'Giving payment status',
            'category' => 'system',
            'enabled' => false,
            'channels' => ['in_app'],
            'audience' => 'event_recipients',
            'critical' => true,
        ]);

        $deliveries = app(DomainNotificationService::class)
            ->user($user, 'PaymentStatusChanged', 'system', 'Payment received', 'Your payment was received.', ['in_app'], [], true);

        $this->assertTrue($deliveries->isEmpty());
        $this->assertDatabaseCount('communication_deliveries', 0);
        $this->assertDatabaseHas('notification_automation_rules', [
            'event_type' => 'PaymentStatusChanged',
            'last_status' => 'skipped',
            'last_recipient_count' => 0,
        ]);
        Notification::assertNothingSent();
    }

    public function test_due_event_reminder_is_sent_once(): void
    {
        Notification::fake();
        $user = $this->administrator();
        NotificationAutomationRule::query()->create([
            'church_id' => $user->church_id,
            'event_type' => 'EventReminderDue',
            'name' => 'Upcoming event reminder',
            'category' => 'events',
            'enabled' => true,
            'channels' => ['in_app'],
            'audience' => 'all_users',
            'reminder_minutes' => 60,
            'critical' => false,
        ]);
        $startsAt = now()->addMinutes(30)->startOfMinute();
        $event = Event::query()->create([
            'church_id' => $user->church_id,
            'title' => 'Evening Service',
            'starts_at' => $startsAt,
            'status' => 'scheduled',
        ]);
        $session = EventSession::query()->create([
            'church_id' => $user->church_id,
            'event_id' => $event->id,
            'title' => 'Evening Service',
            'session_date' => $startsAt->toDateString(),
            'starts_at' => $startsAt->format('H:i'),
            'timezone' => config('app.timezone'),
            'meeting_type' => 'physical',
            'status' => 'scheduled',
        ]);

        $this->artisan('communications:dispatch')->assertSuccessful();
        $this->artisan('communications:dispatch')->assertSuccessful();

        $this->assertSame(1, CommunicationDelivery::query()
            ->where('event_type', 'EventReminderDue')
            ->where('metadata->event_session_id', $session->id)
            ->count());
        $this->assertDatabaseHas('notification_automation_rules', [
            'event_type' => 'EventReminderDue',
            'last_status' => 'success',
            'last_recipient_count' => 1,
        ]);
    }

    public function test_failed_deliveries_can_be_requeued_from_the_dashboard(): void
    {
        $admin = $this->administrator();
        $delivery = CommunicationDelivery::query()->create([
            'church_id' => $admin->church_id,
            'user_id' => $admin->id,
            'channel' => 'in_app',
            'provider' => 'EcclesiaOS',
            'recipient_name' => $admin->name,
            'recipient_contact' => $admin->email,
            'subject' => 'Failed alert',
            'body_excerpt' => 'Failed alert',
            'body' => 'Failed alert',
            'event_type' => 'ProviderTest',
            'category' => 'system',
            'status' => 'failed',
            'retry_status' => 'backoff',
            'attempt' => 2,
            'error' => 'Temporary failure',
        ]);

        $this->actingAs($admin)
            ->post(route('communications.automation.retry-failed'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $delivery->refresh();
        $this->assertSame('queued', $delivery->status);
        $this->assertSame('queued', $delivery->retry_status);
        $this->assertNull($delivery->error);
    }

    private function administrator(): User
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id, 'status' => 'active']);
        $role = Role::query()->create([
            'name' => 'Super Administrator',
            'slug' => 'super-administrator-'.uniqid(),
            'description' => 'Full test access',
        ]);
        $user->roles()->attach($role);

        return $user;
    }
}
