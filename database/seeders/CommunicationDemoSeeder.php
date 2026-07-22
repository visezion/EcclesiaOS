<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Church;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationProviderSetting;
use App\Models\CommunicationRecipient;
use App\Models\CommunicationTemplate;
use App\Models\Member;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CommunicationDemoSeeder extends Seeder
{
    private const CHANNELS = ['in_app', 'email', 'sms', 'whatsapp', 'push'];

    private const TRIGGERS = [
        'EventSessionCreated',
        'EventSessionUpdated',
        'EventSessionCancelled',
        'AttendanceSessionOpened',
        'AttendanceRecorded',
        'VolunteerAssigned',
        'RegistrationConfirmed',
        'FollowUpRequired',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $church = $this->church();
            $campuses = $this->campuses($church);
            $users = $this->users($church, $campuses);
            $members = $this->members($church, $campuses);

            $this->providerSettings($church);
            $templates = $this->templates($church, $campuses, $users);
            $this->preferences($church, $members);
            $this->campaignsAndDeliveries($church, $campuses, $users, $members, $templates);
        });
    }

    private function church(): Church
    {
        return Church::query()->firstOrCreate(
            ['slug' => 'kingdom-life-global-church'],
            [
                'name' => config('church.name', 'Kingdom Life Global Church'),
                'timezone' => config('church.timezone', 'America/Chicago'),
                'currency' => config('church.currency', 'USD'),
                'email' => config('church.contact_email', 'info@klgc.org'),
                'phone' => config('church.contact_phone', '+1 (555) 012-3456'),
                'address' => '123 Kingdom Way, Dallas, TX 75201, USA',
                'settings' => ['branding' => ['primary' => '#6d4aff']],
            ],
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Campus>
     */
    private function campuses(Church $church): \Illuminate\Support\Collection
    {
        $campuses = Campus::query()->where('church_id', $church->id)->orderBy('id')->get();
        if ($campuses->isNotEmpty()) {
            return $campuses;
        }

        return collect([
            ['Headquarters', 'headquarters', 'Main Campus', 'Dallas', 'USA', 'active'],
            ['North Campus', 'north-campus', 'Regional Campus', 'Plano', 'USA', 'active'],
            ['West Campus', 'west-campus', 'Regional Campus', 'Fort Worth', 'USA', 'active'],
            ['Online Campus', 'online-campus', 'Online Campus', 'Online', 'Global', 'active'],
        ])->map(fn (array $row): Campus => Campus::query()->create([
            'church_id' => $church->id,
            'name' => $row[0],
            'slug' => $row[1],
            'type' => $row[2],
            'city' => $row[3],
            'country' => $row[4],
            'address' => $row[3] === 'Online' ? 'Online' : $row[3].', TX',
            'status' => $row[5],
            'metadata' => [],
        ]));
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function users(Church $church, \Illuminate\Support\Collection $campuses): \Illuminate\Support\Collection
    {
        $users = User::query()->where('church_id', $church->id)->orderBy('id')->get();
        if ($users->isNotEmpty()) {
            return $users;
        }

        return collect([
            ['Pastor John', 'admin@kingdomhub.test', 'Senior Pastor'],
            ['Sarah Johnson', 'sarah.johnson@klgc.org', 'Church Administrator'],
            ['Michael Brown', 'michael.brown@klgc.org', 'Communications Lead'],
        ])->map(fn (array $row, int $index): User => User::query()->create([
            'church_id' => $church->id,
            'campus_id' => $campuses[$index % $campuses->count()]->id,
            'name' => $row[0],
            'email' => $row[1],
            'title' => $row[2],
            'phone' => '+1 (555) 010-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'status' => 'active',
            'password' => Hash::make('password'),
        ]));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Member>
     */
    private function members(Church $church, \Illuminate\Support\Collection $campuses): \Illuminate\Support\Collection
    {
        $members = Member::query()->where('church_id', $church->id)->orderBy('id')->get();
        if ($members->count() >= 20) {
            return $members;
        }

        $names = [
            'Sarah Johnson', 'Michael Thompson', 'Emily Davis', 'David Wilson', 'Lisa Martinez', 'James Anderson',
            'Amanda Brown', 'Robert Taylor', 'Jessica Lee', 'Chris Walker', 'Daniel Harris', 'Rachel Green',
            'Kevin White', 'Grace Miller', 'Samuel Clark', 'Naomi Hill', 'Victor Adams', 'Faith Roberts',
            'Joshua Carter', 'Hope Evans', 'Brian Nelson', 'Ruth Turner', 'Caleb Reed', 'Joy Morgan',
        ];

        foreach ($names as $index => $name) {
            [$first, $last] = explode(' ', $name);
            Member::query()->updateOrCreate(
                ['email' => Str::slug($name, '.').'@members.klgc.org'],
                [
                    'church_id' => $church->id,
                    'campus_id' => $campuses[$index % $campuses->count()]->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'phone' => '+1 (555) '.str_pad((string) (230 + $index), 3, '0', STR_PAD_LEFT).'-'.str_pad((string) (4500 + $index), 4, '0', STR_PAD_LEFT),
                    'status' => $index % 11 === 0 ? 'follow-up' : 'active',
                    'joined_at' => now()->subDays(900 - ($index * 19)),
                ],
            );
        }

        return Member::query()->where('church_id', $church->id)->orderBy('id')->get();
    }

    private function providerSettings(Church $church): void
    {
        $settings = [
            'in_app' => ['System Channel', 'In-App Messenger', 1000],
            'email' => ['SendGrid', 'Kingdom Life Global Church <no-reply@klgc.org>', 500],
            'sms' => ['Zender SMS Gateway', 'Kingdom Life +1 (833) 123-4567', 120],
            'whatsapp' => ['Meta WhatsApp', 'Verified Business +1 (833) 123-4567', 90],
            'push' => ['Firebase Cloud Messaging', 'Kingdom Life App', 250],
        ];

        foreach ($settings as $channel => [$provider, $sender, $rateLimit]) {
            CommunicationProviderSetting::query()->updateOrCreate(
                ['church_id' => $church->id, 'channel' => $channel],
                [
                    'provider' => $provider,
                    'enabled' => true,
                    'sender_identity' => $sender,
                    'settings' => [
                        'queue' => $channel.'_queue',
                        'region' => 'US Central',
                        'webhook_event' => 'communication.delivery_status_changed',
                        'endpoint_url' => $channel === 'sms' ? 'https://zender.kingdomhub.test' : null,
                        'device_id' => $channel === 'sms' ? 'android-device-main-campus' : null,
                        'sender_number' => $channel === 'sms' ? '+1 (833) 123-4567' : null,
                        'webhook_url' => url('/webhooks/communications/'.$channel),
                        'workers' => $channel === 'in_app' ? 4 : 8,
                        'daily_limit' => $channel === 'sms' ? 250000 : 100000,
                        'provider_url' => $channel === 'sms' ? 'https://codecanyon.net/item/zender-android-mobile-devices-as-sms-gateway-saas-platform/26594230' : null,
                        'api_key_encrypted' => $channel === 'sms' ? encrypt('demo-zender-api-token') : null,
                        'api_key_last_four' => $channel === 'sms' ? 'oken' : null,
                    ],
                    'rate_limit_per_minute' => $rateLimit,
                    'retry_policy' => $channel === 'in_app' ? 'linear' : 'exponential',
                    'webhook_secret_hash' => hash('sha256', 'demo-'.$channel.'-webhook-secret'),
                    'last_tested_at' => now()->subMinutes(8 + array_search($channel, self::CHANNELS, true)),
                    'last_test_status' => 'success',
                ],
            );
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, CommunicationTemplate>
     */
    private function templates(Church $church, \Illuminate\Support\Collection $campuses, \Illuminate\Support\Collection $users): \Illuminate\Support\Collection
    {
        $rows = [
            ['Event Session Created - Email', 'events', 'EventSessionCreated', 'New Event Session Created: {{eventTitle}}', ['email', 'in_app', 'push'], 'active', 'approved', 126],
            ['Event Session Updated - In-App', 'events', 'EventSessionUpdated', 'Schedule updated for {{eventTitle}}', ['in_app', 'push'], 'active', 'approved', 84],
            ['Event Cancelled - SMS', 'events', 'EventSessionCancelled', 'Event cancelled: {{eventTitle}}', ['sms', 'whatsapp', 'email'], 'active', 'approved', 41],
            ['Attendance Opened Reminder', 'attendance', 'AttendanceSessionOpened', 'Attendance is open for {{eventTitle}}', ['in_app', 'push', 'sms'], 'active', 'approved', 98],
            ['Attendance Confirmation', 'attendance', 'AttendanceRecorded', 'Thanks for checking in', ['in_app', 'email'], 'active', 'approved', 154],
            ['Volunteer Assigned Notice', 'volunteers', 'VolunteerAssigned', 'You have a new volunteer assignment', ['email', 'sms', 'whatsapp', 'push'], 'active', 'approved', 73],
            ['Registration Approved Notice', 'registration', 'RegistrationConfirmed', 'Registration confirmed for {{eventTitle}}', ['email', 'in_app'], 'active', 'approved', 112],
            ['Pastoral Follow-up Reminder', 'care', 'FollowUpRequired', 'Follow-up needed for {{memberName}}', ['in_app', 'email', 'sms'], 'active', 'approved', 66],
            ['Sunday Service Digest', 'events', null, 'This week at Kingdom Life', ['email', 'push'], 'draft', 'pending', 0],
            ['Emergency Closure Notice', 'system', 'EventSessionCancelled', 'Important service update', ['sms', 'whatsapp', 'push', 'email'], 'active', 'approved', 18],
            ['Spanish Event Reminder', 'events', 'EventSessionCreated', 'Nueva sesion: {{eventTitle}}', ['email', 'sms'], 'active', 'approved', 22],
            ['Quiet Hours Follow-up', 'care', 'FollowUpRequired', 'Pastoral care reminder', ['in_app', 'email'], 'inactive', 'approved', 7],
        ];

        foreach ($rows as $index => [$name, $category, $trigger, $subject, $channels, $status, $approval, $usage]) {
            $template = CommunicationTemplate::query()->updateOrCreate(
                ['church_id' => $church->id, 'name' => $name],
                [
                    'campus_id' => $index % 4 === 0 ? $campuses[$index % $campuses->count()]->id : null,
                    'owner_id' => $users[$index % $users->count()]->id,
                    'category' => $category,
                    'trigger_event' => $trigger,
                    'subject' => $subject,
                    'body' => $this->templateBody($category, $trigger),
                    'channels' => $channels,
                    'language' => str_contains($name, 'Spanish') ? 'es' : 'en',
                    'status' => $status,
                    'approval_state' => $approval,
                    'variables' => ['memberName', 'eventTitle', 'eventDate', 'eventTime', 'meetingLink'],
                    'usage_count' => $usage,
                    'last_used_at' => now()->subDays($index + 1),
                ],
            );
            $template->forceFill([
                'created_at' => now()->subDays(45 - $index),
                'updated_at' => now()->subDays($index % 7),
            ])->saveQuietly();
        }

        return CommunicationTemplate::query()->where('church_id', $church->id)->orderBy('id')->get();
    }

    private function templateBody(string $category, ?string $trigger): string
    {
        return match ($category) {
            'attendance' => "Hello {{memberName}},\n\nAttendance is available for {{eventTitle}} on {{eventDate}} at {{eventTime}}.\n\nUse your approved check-in method when you arrive.",
            'care' => "Hello {{memberName}},\n\nA pastoral care follow-up has been scheduled. Please review the care task and respond with any updates.",
            'volunteers' => "Hello {{memberName}},\n\nYou have been assigned to serve for {{eventTitle}}. Please confirm your availability from your account.",
            'registration' => "Hello {{memberName}},\n\nYour registration for {{eventTitle}} is confirmed. Details and meeting access are available in your account.",
            'system' => "Important update for {{eventTitle}}.\n\nPlease review the latest schedule and instructions in EcclesiaOS.",
            default => "Hello {{memberName}},\n\nA new update is available for {{eventTitle}} on {{eventDate}} at {{eventTime}}.\n\n{{meetingLink}}",
        };
    }

    private function preferences(Church $church, \Illuminate\Support\Collection $members): void
    {
        foreach ($members as $index => $member) {
            $channels = match ($index % 5) {
                0 => ['in_app', 'email', 'sms', 'whatsapp', 'push'],
                1 => ['in_app', 'email', 'push'],
                2 => ['in_app', 'sms', 'whatsapp'],
                3 => ['in_app', 'email'],
                default => ['in_app', 'email', 'sms'],
            };

            UserNotificationPreference::query()->updateOrCreate(
                ['church_id' => $church->id, 'member_id' => $member->id],
                [
                    'user_id' => null,
                    'channels' => $channels,
                    'categories' => ['events', 'attendance', 'care', 'volunteers', 'registration', 'system'],
                    'digest_mode' => ['instant', 'daily', 'weekly', 'daily', 'instant'][$index % 5],
                    'quiet_hours_start' => $index % 4 === 0 ? null : ['21:30', '22:00', '23:00'][$index % 3],
                    'quiet_hours_end' => $index % 4 === 0 ? null : ['06:00', '06:30', '07:00'][$index % 3],
                    'language' => $index % 9 === 0 ? 'es' : 'en',
                    'critical_alerts' => $index % 7 !== 0,
                    'opted_out_at' => $index % 13 === 0 ? now()->subDays($index + 1) : null,
                ],
            );
        }
    }

    private function campaignsAndDeliveries(
        Church $church,
        \Illuminate\Support\Collection $campuses,
        \Illuminate\Support\Collection $users,
        \Illuminate\Support\Collection $members,
        \Illuminate\Support\Collection $templates,
    ): void {
        $campaignRows = [
            ['Sunday Service Reminder', 'All Members', 'scheduled', 'scheduled', now()->addDays(1)->setTime(8, 0), ['email', 'sms', 'push']],
            ['Midweek Prayer Meeting', 'Prayer Ministry', 'scheduled', 'scheduled', now()->addDays(2)->setTime(18, 0), ['whatsapp', 'in_app']],
            ['Youth Conference Invite', 'Youth and families', 'scheduled', 'scheduled', now()->addDays(5)->setTime(10, 0), ['email', 'in_app', 'push']],
            ['Mother\'s Day Outreach', 'Women - All Campuses', 'immediate', 'partial', now()->subDays(8)->setTime(9, 0), ['email', 'sms', 'whatsapp', 'push', 'in_app']],
            ['Baptism Class Follow-up', 'Registrants', 'immediate', 'sent', now()->subDays(5)->setTime(16, 0), ['email', 'in_app']],
            ['Volunteer Appreciation', 'Volunteers', 'immediate', 'sent', now()->subDays(3)->setTime(9, 0), ['email', 'whatsapp', 'push']],
            ['Community Outreach Drive', 'Guests and follow-up', 'immediate', 'queued', now()->subHours(3), ['sms', 'whatsapp']],
            ['Pastoral Care Check-in', 'Follow-up Needed', 'scheduled', 'scheduled', now()->addHours(6), ['in_app', 'sms']],
            ['Provider Diagnostic Retry', 'Failed deliveries', 'immediate', 'failed', now()->subHours(8), ['email', 'sms']],
            ['New Member Welcome', 'New Members', 'immediate', 'sent', now()->subDays(1)->setTime(11, 30), ['email', 'in_app', 'push']],
        ];

        foreach ($campaignRows as $index => [$name, $segment, $sendMode, $status, $scheduledAt, $channels]) {
            $template = $templates[$index % $templates->count()];
            $audience = $members->slice($index, min(12, $members->count()))->values();
            if ($audience->isEmpty()) {
                $audience = $members->take(min(12, $members->count()))->values();
            }

            $campaign = CommunicationCampaign::query()->updateOrCreate(
                ['church_id' => $church->id, 'name' => $name],
                [
                    'campus_id' => $index % 3 === 0 ? $campuses[$index % $campuses->count()]->id : null,
                    'template_id' => $template->id,
                    'created_by' => $users[$index % $users->count()]->id,
                    'segment_name' => $segment,
                    'audience_filters' => ['source' => 'communication_demo_seed', 'campus' => $index % 3 === 0 ? $campuses[$index % $campuses->count()]->name : 'All Campuses'],
                    'channels' => $channels,
                    'subject' => $template->subject,
                    'body' => $template->body,
                    'send_mode' => $sendMode,
                    'scheduled_at' => $sendMode === 'scheduled' ? $scheduledAt : null,
                    'status' => $status,
                    'recipient_count' => $audience->count(),
                    'sent_count' => in_array($status, ['sent', 'partial', 'failed'], true) ? $audience->count() * count($channels) : 0,
                    'delivered_count' => in_array($status, ['sent', 'partial'], true) ? max(1, (int) floor($audience->count() * count($channels) * 0.9)) : 0,
                    'failed_count' => in_array($status, ['partial', 'failed'], true) ? max(1, (int) floor($audience->count() * 0.3)) : 0,
                    'opened_count' => in_array($status, ['sent', 'partial'], true) ? max(1, (int) floor($audience->count() * 0.55)) : 0,
                    'clicked_count' => in_array($status, ['sent', 'partial'], true) ? max(1, (int) floor($audience->count() * 0.18)) : 0,
                ],
            );
            $campaign->forceFill([
                'created_at' => $sendMode === 'scheduled' ? now()->subDays($index % 4) : Carbon::parse($scheduledAt),
                'updated_at' => now()->subHours($index + 1),
            ])->saveQuietly();

            foreach ($audience as $memberIndex => $member) {
                $preference = UserNotificationPreference::query()
                    ->where('church_id', $church->id)
                    ->where('member_id', $member->id)
                    ->first();

                CommunicationRecipient::query()->updateOrCreate(
                    ['communication_campaign_id' => $campaign->id, 'member_id' => $member->id],
                    [
                        'user_id' => null,
                        'name' => trim($member->first_name.' '.$member->last_name),
                        'email' => $member->email,
                        'phone' => $member->phone,
                        'preferences' => [
                            'channels' => $preference?->channels ?? self::CHANNELS,
                        ],
                        'status' => in_array($status, ['sent', 'partial'], true) ? 'sent' : ($status === 'failed' ? 'failed' : 'queued'),
                    ],
                );

                foreach ($channels as $channelIndex => $channel) {
                    $this->delivery($church, $campaign, $template, $member, $channel, $index, $memberIndex, $channelIndex, $status);
                }
            }
        }

        $this->backgroundDeliveryHistory($church, $members, $templates);
    }

    private function delivery(
        Church $church,
        CommunicationCampaign $campaign,
        CommunicationTemplate $template,
        Member $member,
        string $channel,
        int $campaignIndex,
        int $memberIndex,
        int $channelIndex,
        string $campaignStatus,
    ): void {
        $status = match ($campaignStatus) {
            'queued', 'scheduled' => 'queued',
            'failed' => 'failed',
            'partial' => ($memberIndex + $channelIndex) % 8 === 0 ? 'failed' : 'delivered',
            default => ($memberIndex + $channelIndex) % 17 === 0 ? 'failed' : 'delivered',
        };
        $createdAt = now()->subDays(($campaignIndex * 2 + $memberIndex + $channelIndex) % 30)->subMinutes($memberIndex * 7);
        $providerMessageId = 'DEMO-'.Str::upper($channel).'-'.$campaign->id.'-'.$member->id.'-'.$channelIndex;

        CommunicationDelivery::query()->updateOrCreate(
            ['provider_message_id' => $providerMessageId],
            [
                'church_id' => $church->id,
                'communication_campaign_id' => $campaign->id,
                'communication_template_id' => $template->id,
                'member_id' => $member->id,
                'channel' => $channel,
                'provider' => $this->providerFor($channel),
                'recipient_name' => trim($member->first_name.' '.$member->last_name),
                'recipient_contact' => in_array($channel, ['sms', 'whatsapp'], true) ? $member->phone : $member->email,
                'subject' => $campaign->subject,
                'body_excerpt' => Str::limit(strip_tags($campaign->body), 180),
                'event_type' => $template->trigger_event ?? 'BulkCampaign',
                'status' => $status,
                'retry_status' => $status === 'failed' ? 'queued' : 'none',
                'attempt' => $status === 'failed' ? (($memberIndex % 3) + 1) : 1,
                'latency_ms' => $status === 'queued' ? null : 120 + (($campaignIndex + $memberIndex + $channelIndex) * 37) % 1800,
                'response_code' => $status === 'failed' ? $this->failureCode($channelIndex) : '200 OK',
                'error' => $status === 'failed' ? $this->failureReason($channelIndex) : null,
                'sent_at' => $status === 'queued' ? null : $createdAt->copy()->addSeconds(15),
                'delivered_at' => $status === 'delivered' ? $createdAt->copy()->addSeconds(55) : null,
                'opened_at' => $status === 'delivered' && ($memberIndex + $channelIndex) % 3 !== 0 ? $createdAt->copy()->addMinutes(12) : null,
                'read_at' => $status === 'delivered' && $channel === 'in_app' && $memberIndex % 2 === 0 ? $createdAt->copy()->addMinutes(9) : null,
            ],
        )->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt->copy()->addMinutes(15),
        ])->saveQuietly();
    }

    private function backgroundDeliveryHistory(Church $church, \Illuminate\Support\Collection $members, \Illuminate\Support\Collection $templates): void
    {
        foreach (range(1, 220) as $index) {
            $member = $members[($index - 1) % $members->count()];
            $template = $templates[($index - 1) % $templates->count()];
            $channel = self::CHANNELS[$index % count(self::CHANNELS)];
            $status = $index % 19 === 0 ? 'failed' : ($index % 11 === 0 ? 'queued' : 'delivered');
            $createdAt = now()->subDays($index % 30)->subMinutes($index * 3);
            $providerMessageId = 'DEMO-HISTORY-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT);

            CommunicationDelivery::query()->updateOrCreate(
                ['provider_message_id' => $providerMessageId],
                [
                    'church_id' => $church->id,
                    'communication_campaign_id' => null,
                    'communication_template_id' => $template->id,
                    'member_id' => $member->id,
                    'channel' => $channel,
                    'provider' => $this->providerFor($channel),
                    'recipient_name' => trim($member->first_name.' '.$member->last_name),
                    'recipient_contact' => in_array($channel, ['sms', 'whatsapp'], true) ? $member->phone : $member->email,
                    'subject' => $template->subject,
                    'body_excerpt' => Str::limit(strip_tags($template->body), 180),
                    'event_type' => self::TRIGGERS[$index % count(self::TRIGGERS)],
                    'status' => $status,
                    'retry_status' => $status === 'failed' ? 'queued' : ($status === 'queued' ? 'scheduled' : 'none'),
                    'attempt' => $status === 'failed' ? (($index % 4) + 1) : 1,
                    'latency_ms' => $status === 'queued' ? null : 90 + (($index * 23) % 2200),
                    'response_code' => $status === 'failed' ? $this->failureCode($index) : '200 OK',
                    'error' => $status === 'failed' ? $this->failureReason($index) : null,
                    'sent_at' => $status === 'queued' ? null : $createdAt->copy()->addSeconds(12),
                    'delivered_at' => $status === 'delivered' ? $createdAt->copy()->addSeconds(45) : null,
                    'opened_at' => $status === 'delivered' && $index % 3 !== 0 ? $createdAt->copy()->addMinutes(8) : null,
                    'read_at' => $status === 'delivered' && $channel === 'in_app' && $index % 2 === 0 ? $createdAt->copy()->addMinutes(5) : null,
                ],
            )->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addMinutes(10),
            ])->saveQuietly();
        }
    }

    private function providerFor(string $channel): string
    {
        return match ($channel) {
            'email' => 'SendGrid',
            'sms' => 'Twilio',
            'whatsapp' => 'Meta WhatsApp',
            'push' => 'Firebase Cloud Messaging',
            default => 'System Channel',
        };
    }

    private function failureCode(int $index): string
    {
        return ['550 Invalid recipient', '429 Too Many Requests', 'Webhook timeout', 'Provider rejected message'][$index % 4];
    }

    private function failureReason(int $index): string
    {
        return ['Invalid recipient address', 'Provider rate limit exceeded', 'Provider timeout', 'User opted out of this channel'][$index % 4];
    }
}
