<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\CommunicationDelivery;
use App\Models\CommunicationProviderSetting;
use App\Models\CommunicationWhatsAppGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class ZenderWhatsAppNotifier
{
    /**
     * Send a WhatsApp notification to enabled Zender groups in the requested scope.
     *
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function notify(
        int $churchId,
        string $message,
        string $eventType,
        ?int $campusId = null,
        ?int $ministryId = null,
        ?string $subject = null,
    ): array {
        $setting = CommunicationProviderSetting::query()
            ->where('church_id', $churchId)
            ->where('channel', 'whatsapp')
            ->first();

        if (! $setting?->enabled || ! Str::contains(Str::lower((string) $setting->provider), 'zender')) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 1];
        }

        $siteUrl = rtrim((string) ($setting->settings['endpoint_url'] ?? ''), '/');
        $accountId = (string) ($setting->settings['account_id'] ?? '');
        $apiKey = $this->apiKey($setting);

        if ($siteUrl === '' || $accountId === '' || $apiKey === null) {
            return ['sent' => 0, 'failed' => 1, 'skipped' => 0];
        }

        $groups = $this->groupsForScope($churchId, $campusId, $ministryId);
        if ($groups->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 1];
        }

        $sent = 0;
        $failed = 0;

        foreach ($groups as $group) {
            $startedAt = microtime(true);
            try {
                $response = Http::acceptJson()
                    ->timeout(25)
                    ->connectTimeout(10)
                    ->withHeaders(['User-Agent' => 'EcclesiaOS Zender Notifier'])
                    ->asForm()
                    ->post($siteUrl.'/api/send/whatsapp', [
                        'secret' => $apiKey,
                        'account' => $accountId,
                        'recipient' => $group->provider_group_id,
                        'type' => 'text',
                        'message' => $this->cleanMessage($message),
                        'priority' => 2,
                    ]);

                $latency = (int) round((microtime(true) - $startedAt) * 1000);
                $json = $response->json();
                $accepted = $response->successful() && $this->accepted(is_array($json) ? $json : null);

                CommunicationDelivery::query()->create([
                    'church_id' => $churchId,
                    'communication_whatsapp_group_id' => $group->id,
                    'channel' => 'whatsapp',
                    'provider' => 'zender',
                    'recipient_name' => $group->name,
                    'recipient_contact' => $group->provider_group_id,
                    'subject' => $subject,
                    'body_excerpt' => Str::limit($message, 240),
                    'event_type' => $eventType,
                    'status' => $accepted ? 'delivered' : 'failed',
                    'retry_status' => $accepted ? 'none' : 'pending',
                    'attempt' => 1,
                    'latency_ms' => $latency,
                    'provider_message_id' => $this->messageId(is_array($json) ? $json : null),
                    'response_code' => (string) $response->status(),
                    'error' => $accepted ? null : Str::limit((string) (is_array($json) ? (data_get($json, 'message') ?? json_encode($json)) : $response->body()), 1000),
                    'sent_at' => Carbon::now(),
                    'delivered_at' => $accepted ? Carbon::now() : null,
                ]);

                $accepted ? $sent++ : $failed++;
            } catch (Throwable $exception) {
                CommunicationDelivery::query()->create([
                    'church_id' => $churchId,
                    'communication_whatsapp_group_id' => $group->id,
                    'channel' => 'whatsapp',
                    'provider' => 'zender',
                    'recipient_name' => $group->name,
                    'recipient_contact' => $group->provider_group_id,
                    'subject' => $subject,
                    'body_excerpt' => Str::limit($message, 240),
                    'event_type' => $eventType,
                    'status' => 'failed',
                    'retry_status' => 'pending',
                    'attempt' => 1,
                    'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error' => Str::limit($exception->getMessage(), 1000),
                    'sent_at' => Carbon::now(),
                ]);

                $failed++;
            }
        }

        return compact('sent', 'failed') + ['skipped' => 0];
    }

    /**
     * @return Collection<int, CommunicationWhatsAppGroup>
     */
    private function groupsForScope(int $churchId, ?int $campusId, ?int $ministryId)
    {
        $query = CommunicationWhatsAppGroup::query()
            ->where('church_id', $churchId)
            ->where('provider', 'zender')
            ->where('enabled', true);

        if ($ministryId !== null) {
            return $query->where(function ($scope) use ($ministryId, $campusId): void {
                $scope->where('target_scope', 'ministry')->where('ministry_id', $ministryId);
                if ($campusId !== null) {
                    $scope->orWhere(function ($campusScope) use ($campusId): void {
                        $campusScope->where('target_scope', 'campus')->where('campus_id', $campusId);
                    });
                }
                $scope->orWhere('target_scope', 'church');
            })->orderBy('name')->get();
        }

        if ($campusId !== null) {
            return $query->where(function ($scope) use ($campusId): void {
                $scope->where('target_scope', 'campus')->where('campus_id', $campusId)
                    ->orWhere('target_scope', 'church');
            })->orderBy('name')->get();
        }

        return $query->where('target_scope', 'church')->orderBy('name')->get();
    }

    private function cleanMessage(string $message): string
    {
        return trim((string) Str::of(strip_tags($message))->replaceMatches('/\s+/', ' '));
    }

    private function apiKey(CommunicationProviderSetting $setting): ?string
    {
        $encrypted = $setting->settings['api_key_encrypted'] ?? null;
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function accepted(?array $json): bool
    {
        if ($json === null) {
            return true;
        }

        $status = data_get($json, 'status');
        if (is_numeric($status) && (int) $status >= 400) {
            return false;
        }

        if (is_string($status) && in_array(Str::lower($status), ['error', 'fail', 'failed', 'false'], true)) {
            return false;
        }

        return data_get($json, 'data') !== false && data_get($json, 'success') !== false;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function messageId(?array $json): ?string
    {
        return data_get($json, 'data.id')
            ?? data_get($json, 'data.message_id')
            ?? data_get($json, 'data.messageId')
            ?? data_get($json, 'message_id')
            ?? data_get($json, 'messageId');
    }
}
