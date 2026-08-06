<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\CommunicationDelivery;
use App\Models\CommunicationProviderSetting;
use App\Models\User;
use App\Notifications\CommunicationDeliveryNotification;
use App\Support\SafeOutboundUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class CommunicationDeliveryDispatcher
{
    public function dispatch(CommunicationDelivery $delivery): CommunicationDelivery
    {
        if ($delivery->status === 'delivered') {
            return $delivery;
        }

        if ($delivery->available_at?->isFuture()) {
            return $delivery;
        }

        $startedAt = microtime(true);
        $delivery->forceFill([
            'status' => 'processing',
            'retry_status' => 'processing',
            'sent_at' => now(),
            'error' => null,
        ])->save();

        try {
            $outcome = match ($delivery->channel) {
                'in_app' => $this->sendInApp($delivery),
                'sms', 'whatsapp' => $this->sendZender($delivery),
                default => $this->failed(
                    'Provider not implemented',
                    Str::headline($delivery->channel).' delivery is not implemented in this stage.',
                ),
            };
        } catch (Throwable $exception) {
            $outcome = $this->failed('Connection failed', Str::limit($exception->getMessage(), 1000));
        }

        $latency = (int) round((microtime(true) - $startedAt) * 1000);
        $delivery->forceFill($outcome + [
            'latency_ms' => $outcome['latency_ms'] ?? $latency,
            'available_at' => null,
        ])->save();

        return $delivery->refresh();
    }

    private function sendInApp(CommunicationDelivery $delivery): array
    {
        $user = $delivery->user
            ?? $delivery->member?->userAccount
            ?? User::query()
                ->where('church_id', $delivery->church_id)
                ->whereRaw('LOWER(email) = ?', [strtolower((string) $delivery->recipient_contact)])
                ->first();

        if (! $user) {
            if ($delivery->member_id !== null) {
                return $this->delivered('Member notification inbox');
            }

            return $this->failed('Missing recipient account', 'The recipient does not have a linked EcclesiaOS user account.');
        }

        if ($user->status !== 'active') {
            return $this->failed('Inactive recipient', 'The recipient user account is not active.');
        }

        if ($delivery->user_id === null) {
            $delivery->forceFill(['user_id' => $user->id])->save();
        }

        $user->notify(new CommunicationDeliveryNotification($delivery));

        return $this->delivered('Internal notification');
    }

    private function sendZender(CommunicationDelivery $delivery): array
    {
        $setting = CommunicationProviderSetting::query()
            ->where('church_id', $delivery->church_id)
            ->where('channel', $delivery->channel)
            ->first();

        if (! $setting?->enabled || ! Str::contains(Str::lower((string) $setting->provider), 'zender')) {
            return $this->failed('Provider disabled', 'An enabled Zender provider is required.');
        }

        if (blank($delivery->recipient_contact)) {
            return $this->failed('Missing recipient', 'The recipient contact is missing.');
        }

        $settings = $setting->settings ?? [];
        $siteUrl = $this->siteUrl($settings['endpoint_url'] ?? null);
        $apiKey = $this->apiKey($setting);
        if ($siteUrl === null || $apiKey === null) {
            return $this->failed('Configuration check failed', 'Zender site URL and API key are required.');
        }

        $message = trim((string) Str::of(strip_tags($delivery->body ?: $delivery->body_excerpt ?: ''))->replaceMatches('/\s+/', ' '));
        $payload = $delivery->channel === 'whatsapp'
            ? [
                'secret' => $apiKey,
                'account' => (string) ($settings['account_id'] ?? ''),
                'recipient' => $delivery->recipient_contact,
                'type' => 'text',
                'message' => $message,
                'priority' => 2,
            ]
            : array_filter([
                'secret' => $apiKey,
                'mode' => filled($settings['gateway_id'] ?? null) ? 'credits' : 'devices',
                'phone' => $delivery->recipient_contact,
                'message' => $message,
                'priority' => 2,
                'device' => $settings['device_id'] ?? null,
                'gateway' => $settings['gateway_id'] ?? null,
                'sim' => filled($settings['sim_slot'] ?? null) ? (int) $settings['sim_slot'] : null,
            ], fn (mixed $value): bool => $value !== null && $value !== '');

        $response = $this->client($siteUrl)
            ->asForm()
            ->post($siteUrl.'/api/send/'.$delivery->channel, $payload);
        $json = $response->json();
        $accepted = $response->successful() && $this->accepted(is_array($json) ? $json : null);

        if (! $accepted) {
            return $this->failed(
                'HTTP '.$response->status(),
                Str::limit((string) (is_array($json) ? (data_get($json, 'message') ?? json_encode($json)) : $response->body()), 1000),
            );
        }

        return $this->delivered(
            'HTTP '.$response->status(),
            data_get($json, 'data.id') ?? data_get($json, 'data.message_id') ?? data_get($json, 'message_id'),
        );
    }

    private function client(string $siteUrl): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(25)
            ->connectTimeout(10)
            ->withHeaders(['User-Agent' => 'EcclesiaOS Communication Dispatcher'])
            ->withOptions(SafeOutboundUrl::requestOptions($siteUrl));
    }

    private function siteUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return SafeOutboundUrl::normalize($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function apiKey(CommunicationProviderSetting $setting): ?string
    {
        $encrypted = data_get($setting->settings, 'api_key_encrypted');
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }
    }

    private function accepted(?array $json): bool
    {
        if ($json === null) {
            return true;
        }

        $status = data_get($json, 'status');

        return ! ((is_numeric($status) && (int) $status >= 400)
            || (is_string($status) && in_array(Str::lower($status), ['error', 'fail', 'failed', 'false'], true))
            || data_get($json, 'data') === false
            || data_get($json, 'success') === false);
    }

    private function delivered(string $responseCode, ?string $providerMessageId = null): array
    {
        return [
            'status' => 'delivered',
            'retry_status' => 'none',
            'response_code' => $responseCode,
            'provider_message_id' => $providerMessageId,
            'error' => null,
            'delivered_at' => now(),
        ];
    }

    private function failed(string $responseCode, string $error): array
    {
        return [
            'status' => 'failed',
            'retry_status' => 'failed',
            'response_code' => $responseCode,
            'error' => $error,
            'delivered_at' => null,
        ];
    }
}
