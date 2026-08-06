<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationProviderSetting;
use App\Models\CommunicationRecipient;
use App\Models\CommunicationWhatsAppGroup;
use Illuminate\Support\Str;

final class CommunicationCampaignDispatcher
{
    public function __construct(private readonly CommunicationDeliveryDispatcher $deliveries) {}

    public function dispatch(CommunicationCampaign $campaign): CommunicationCampaign
    {
        $campaign->loadMissing(['recipients.member.userAccount', 'recipients.user', 'template']);
        $settings = CommunicationProviderSetting::query()
            ->where('church_id', $campaign->church_id)
            ->get()
            ->keyBy('channel');
        $sent = $delivered = $failed = 0;

        foreach ($campaign->recipients as $recipient) {
            $recipientFailed = false;
            foreach ($campaign->channels ?? [] as $channel) {
                if (! $this->recipientAllows($recipient, $channel)) {
                    continue;
                }

                $delivery = $this->newDelivery(
                    campaign: $campaign,
                    recipient: $recipient,
                    channel: $channel,
                    provider: $settings[$channel]?->provider ?? ($channel === 'in_app' ? 'EcclesiaOS' : Str::headline($channel)),
                );
                $sent++;
                $delivery = $this->deliveries->dispatch($delivery);
                $delivered += $delivery->status === 'delivered' ? 1 : 0;
                $failed += $delivery->status === 'failed' ? 1 : 0;
                $recipientFailed = $recipientFailed || $delivery->status === 'failed';
            }
            $recipient->update(['status' => $recipientFailed ? 'failed' : 'sent']);
        }

        if (in_array('whatsapp', $campaign->channels ?? [], true)) {
            foreach ($this->whatsappGroups($campaign) as $group) {
                $delivery = CommunicationDelivery::query()->create([
                    'church_id' => $campaign->church_id,
                    'communication_campaign_id' => $campaign->id,
                    'communication_template_id' => $campaign->template_id,
                    'communication_whatsapp_group_id' => $group->id,
                    'channel' => 'whatsapp',
                    'provider' => $settings['whatsapp']?->provider ?? 'Zender WhatsApp Gateway',
                    'recipient_name' => $group->name,
                    'recipient_contact' => $group->provider_group_id,
                    'subject' => $campaign->subject,
                    'body_excerpt' => Str::limit(strip_tags($campaign->body), 180),
                    'body' => $campaign->body,
                    'event_type' => $campaign->template?->trigger_event ?? 'WhatsAppGroupCampaign',
                    'category' => $campaign->template?->category,
                    'status' => 'queued',
                    'retry_status' => 'queued',
                    'attempt' => 1,
                ]);
                $sent++;
                $delivery = $this->deliveries->dispatch($delivery);
                $delivered += $delivery->status === 'delivered' ? 1 : 0;
                $failed += $delivery->status === 'failed' ? 1 : 0;
            }
        }

        $campaign->update([
            'status' => $failed > 0 && $delivered > 0 ? 'partial' : ($failed > 0 ? 'failed' : 'sent'),
            'sent_count' => $sent,
            'delivered_count' => $delivered,
            'failed_count' => $failed,
            'opened_count' => 0,
            'clicked_count' => 0,
        ]);

        return $campaign->refresh();
    }

    private function newDelivery(CommunicationCampaign $campaign, CommunicationRecipient $recipient, string $channel, string $provider): CommunicationDelivery
    {
        $user = $recipient->user ?? $recipient->member?->userAccount;

        return CommunicationDelivery::query()->create([
            'church_id' => $campaign->church_id,
            'communication_campaign_id' => $campaign->id,
            'communication_template_id' => $campaign->template_id,
            'member_id' => $recipient->member_id,
            'user_id' => $user?->id,
            'channel' => $channel,
            'provider' => $provider,
            'recipient_name' => $recipient->name,
            'recipient_contact' => in_array($channel, ['sms', 'whatsapp'], true) ? $recipient->phone : $recipient->email,
            'subject' => $campaign->subject,
            'body_excerpt' => Str::limit(strip_tags($campaign->body), 180),
            'body' => $campaign->body,
            'event_type' => $campaign->template?->trigger_event ?? 'BulkCampaign',
            'category' => $campaign->template?->category,
            'status' => 'queued',
            'retry_status' => 'queued',
            'attempt' => 1,
        ]);
    }

    private function recipientAllows(CommunicationRecipient $recipient, string $channel): bool
    {
        $channels = data_get($recipient->preferences, 'channels', []);

        return $channels === [] || in_array($channel, $channels, true);
    }

    private function whatsappGroups(CommunicationCampaign $campaign)
    {
        $ids = collect(data_get($campaign->audience_filters, 'whatsapp_group_ids', []))->filter()->map(fn (mixed $id): int => (int) $id);
        if ($ids->isEmpty()) {
            return collect();
        }

        return CommunicationWhatsAppGroup::query()
            ->where('church_id', $campaign->church_id)
            ->where('enabled', true)
            ->whereIn('id', $ids)
            ->get();
    }
}
