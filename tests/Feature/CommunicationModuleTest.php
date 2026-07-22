<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationTemplate;
use App\Models\Member;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommunicationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_communication_pages_render_from_database(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        foreach ([
            'communications.index' => 'Communications & Notifications',
            'communications.notifications' => 'Notifications Center',
            'communications.templates' => 'Message Templates',
            'communications.scheduled' => 'Scheduled Messages',
            'communications.bulk' => 'Bulk Messaging',
            'communications.delivery-logs' => 'Delivery Logs',
            'communications.preferences' => 'User Notification Preferences',
            'communications.integrations' => 'Channel Integrations',
        ] as $route => $text) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertOk()
                ->assertSee($text, false);
        }
    }

    public function test_templates_campaigns_preferences_integrations_and_delivery_retry_are_functional(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $member = Member::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('communications.templates.store'), [
                'name' => 'Attendance Opened Email',
                'category' => 'attendance',
                'trigger_event' => 'AttendanceSessionOpened',
                'subject' => 'Attendance is open',
                'body' => 'Hello {{memberName}}, attendance is open.',
                'channels' => ['in_app'],
                'language' => 'en',
                'status' => 'active',
                'approval_state' => 'approved',
            ])
            ->assertRedirect();

        $template = CommunicationTemplate::query()->where('name', 'Attendance Opened Email')->firstOrFail();
        $this->assertSame(['memberName'], $template->variables);

        $this->actingAs($user)
            ->put(route('communications.templates.update', $template), [
                'name' => 'Attendance Opened Email Updated',
                'category' => 'attendance',
                'trigger_event' => 'AttendanceSessionOpened',
                'subject' => 'Attendance is open now',
                'body' => 'Hello {{memberName}}, please check in.',
                'channels' => ['in_app', 'email'],
                'language' => 'en',
                'status' => 'active',
                'approval_state' => 'approved',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('communications.templates.clone', $template->fresh()))
            ->assertRedirect();

        $this->assertDatabaseHas('communication_templates', ['name' => 'Copy of Attendance Opened Email Updated']);

        $this->actingAs($user)
            ->post(route('communications.campaigns.store'), [
                'name' => 'Member Check-in Reminder',
                'segment_name' => 'All active members',
                'template_id' => $template->id,
                'channels' => ['in_app'],
                'subject' => 'Check-in reminder',
                'body' => 'Please check in for service.',
                'send_mode' => 'immediate',
                'member_status' => $member->status,
            ])
            ->assertRedirect();

        $campaign = CommunicationCampaign::query()->where('name', 'Member Check-in Reminder')->firstOrFail();
        $this->assertGreaterThan(0, $campaign->recipient_count);
        $this->assertDatabaseHas('communication_deliveries', [
            'communication_campaign_id' => $campaign->id,
            'channel' => 'in_app',
            'status' => 'delivered',
        ]);

        $this->actingAs($user)->get(route('communications.preferences'))->assertOk();
        $preference = UserNotificationPreference::query()->where('member_id', $member->id)->firstOrFail();

        $this->actingAs($user)
            ->put(route('communications.preferences.update', $preference), [
                'channels' => ['in_app', 'email'],
                'categories' => ['events', 'attendance'],
                'digest_mode' => 'daily',
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '06:00',
                'language' => 'en',
                'critical_alerts' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('daily', $preference->fresh()->digest_mode);

        $this->actingAs($user)
            ->put(route('communications.integrations.update'), [
                'providers' => [
                    'email' => [
                        'enabled' => '1',
                        'provider' => 'SMTP / Mailer',
                        'sender_identity' => 'Kingdom Life',
                        'rate_limit_per_minute' => 250,
                        'retry_policy' => 'exponential',
                        'webhook_secret' => 'secret-value',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('communications.integrations.test', 'email'))
            ->assertRedirect();

        $failed = CommunicationDelivery::query()->create([
            'church_id' => $user->church_id,
            'member_id' => $member->id,
            'channel' => 'sms',
            'provider' => 'SMS Gateway',
            'recipient_name' => $member->first_name.' '.$member->last_name,
            'recipient_contact' => $member->phone,
            'subject' => 'Retry test',
            'body_excerpt' => 'Retry this delivery.',
            'event_type' => 'ProviderTest',
            'status' => 'failed',
            'retry_status' => 'none',
            'attempt' => 1,
            'error' => 'Provider disabled',
        ]);

        $this->actingAs($user)
            ->post(route('communications.delivery-logs.retry', $failed))
            ->assertRedirect();

        $this->assertSame('queued', $failed->fresh()->retry_status);

        $this->actingAs($user)
            ->get(route('communications.delivery-logs.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
