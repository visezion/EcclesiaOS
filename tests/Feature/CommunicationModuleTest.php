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

        $this->actingAs($user)
            ->get(route('communications.index'))
            ->assertOk()
            ->assertSee('Queued Domain Events', false)
            ->assertSee('Queued Listeners', false)
            ->assertSee('Operational Insights', false)
            ->assertSee('Communication History Summary', false)
            ->assertSee('Scheduled Messages', false);
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
            ->post(route('communications.templates.test-send', $template->fresh()))
            ->assertRedirect();

        $this->assertDatabaseHas('communication_deliveries', [
            'communication_template_id' => $template->id,
            'event_type' => 'AttendanceSessionOpened',
        ]);

        $this->actingAs($user)
            ->get(route('communications.templates.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

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
                'person_name' => 'Updated Preference Member',
                'person_email' => 'updated.preference.member@example.com',
                'person_phone' => '+1 (555) 777-8888',
                'person_status' => 'active',
                'campus_id' => $member->campus_id,
            ])
            ->assertRedirect();

        $this->assertSame('daily', $preference->fresh()->digest_mode);
        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => 'Updated',
            'last_name' => 'Preference Member',
            'email' => 'updated.preference.member@example.com',
            'phone' => '+1 (555) 777-8888',
        ]);

        $this->actingAs($user)
            ->put(route('communications.preferences.update', $preference->fresh()), [
                'categories' => ['events', 'attendance', 'care'],
                'category_channels' => [
                    'events' => ['in_app', 'email', 'push'],
                    'attendance' => ['in_app', 'sms'],
                    'care' => ['whatsapp'],
                ],
                'digest_mode' => 'weekly',
                'quiet_hours_start' => '21:00',
                'quiet_hours_end' => '07:00',
                'language' => 'en',
                'critical_alerts' => '1',
            ])
            ->assertRedirect();

        $preference->refresh();
        $this->assertSame(['events', 'attendance', 'care'], $preference->categories);
        $this->assertSame(['in_app', 'email', 'push'], $preference->category_channels['events']);
        $this->assertSame(['in_app', 'sms'], $preference->category_channels['attendance']);
        $this->assertSame(['whatsapp'], $preference->category_channels['care']);
        $this->assertEqualsCanonicalizing(['in_app', 'email', 'push', 'sms', 'whatsapp'], $preference->channels);

        $this->actingAs($user)
            ->get(route('communications.preferences.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->actingAs($user)
            ->post(route('communications.preferences.defaults'), ['selected' => [$preference->opaqueId()]])
            ->assertRedirect();

        $this->assertSame('instant', $preference->fresh()->digest_mode);

        $this->actingAs($user)
            ->post(route('communications.preferences.reminders'), ['selected' => [$preference->opaqueId()]])
            ->assertRedirect();

        $this->assertDatabaseHas('communication_deliveries', [
            'member_id' => $member->id,
            'event_type' => 'PreferenceReminder',
            'status' => 'queued',
        ]);

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

        $this->actingAs($user)
            ->put(route('communications.integrations.update'), [
                'providers' => [
                    'sms' => [
                        'enabled' => '1',
                        'provider' => 'Zender SMS Gateway',
                        'sender_identity' => 'Kingdom Life +1 (833) 123-4567',
                        'rate_limit_per_minute' => 120,
                        'retry_policy' => 'exponential',
                        'webhook_secret' => 'zender-webhook-secret',
                        'endpoint_url' => 'https://zender.kingdomhub.test',
                        'api_key' => 'zender-live-token',
                        'account_id' => 'klgc-zender',
                        'device_id' => 'android-device-main-campus',
                        'sender_number' => '+1 (833) 123-4567',
                        'webhook_url' => 'https://kingdomhub.test/webhooks/zender',
                        'queue' => 'sms_queue',
                        'workers' => 8,
                        'daily_limit' => 250000,
                        'region' => 'US Central',
                    ],
                ],
            ])
            ->assertRedirect();

        $smsProvider = \App\Models\CommunicationProviderSetting::query()
            ->where('church_id', $user->church_id)
            ->where('channel', 'sms')
            ->firstOrFail();

        $this->assertSame('Zender SMS Gateway', $smsProvider->provider);
        $this->assertSame('https://zender.kingdomhub.test', $smsProvider->settings['endpoint_url']);
        $this->assertSame('android-device-main-campus', $smsProvider->settings['device_id']);
        $this->assertSame('oken', $smsProvider->settings['api_key_last_four']);

        $this->actingAs($user)
            ->post(route('communications.integrations.test', 'sms'))
            ->assertRedirect();

        $this->assertSame('success', $smsProvider->fresh()->last_test_status);

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

        $unread = CommunicationDelivery::query()->create([
            'church_id' => $user->church_id,
            'member_id' => $member->id,
            'channel' => 'in_app',
            'provider' => 'System Channel',
            'recipient_name' => $member->first_name.' '.$member->last_name,
            'recipient_contact' => $member->email,
            'subject' => 'Read test',
            'body_excerpt' => 'Mark this notification as read.',
            'event_type' => 'EventSessionCreated',
            'status' => 'delivered',
            'retry_status' => 'none',
            'attempt' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('communications.notifications.read', $unread))
            ->assertRedirect();

        $this->assertNotNull($unread->fresh()->read_at);

        $old = CommunicationDelivery::query()->create([
            'church_id' => $user->church_id,
            'member_id' => $member->id,
            'channel' => 'in_app',
            'provider' => 'System Channel',
            'recipient_name' => $member->first_name.' '.$member->last_name,
            'recipient_contact' => $member->email,
            'subject' => 'Archive test',
            'body_excerpt' => 'Archive this old notification.',
            'event_type' => 'FollowUpRequired',
            'status' => 'delivered',
            'retry_status' => 'none',
            'attempt' => 1,
        ]);
        $old->forceFill(['created_at' => now()->subDays(45), 'updated_at' => now()->subDays(45)])->save();

        $this->actingAs($user)
            ->post(route('communications.notifications.archive-old'))
            ->assertRedirect();

        $this->assertNotNull($old->fresh()->read_at);

        $this->actingAs($user)
            ->get(route('communications.delivery-logs.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
