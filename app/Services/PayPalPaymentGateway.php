<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use App\Models\PaymentGatewayTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PayPalPaymentGateway
{
    public function __construct(private readonly PaymentGatewaySettings $settings) {}

    public function isConfigured(?Church $church = null): bool
    {
        return $this->settings->isConfigured($church ?? Church::query()->first(), 'paypal');
    }

    public function createCheckout(PaymentGatewayTransaction $transaction, string $successUrl, string $cancelUrl): array
    {
        $configuration = $this->configuration($transaction->church);
        $response = $this->authorized($configuration)->withHeaders(['PayPal-Request-Id' => $transaction->reference])
            ->post($this->base($configuration).'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $transaction->reference,
                    'custom_id' => $transaction->reference,
                    'description' => 'Gift to '.$transaction->church->name,
                    'amount' => ['currency_code' => $transaction->currency, 'value' => number_format((float) $transaction->amount, 2, '.', '')],
                ]],
                'payment_source' => ['paypal' => ['experience_context' => [
                    'user_action' => 'PAY_NOW',
                    'return_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                ]]],
            ])->throw()->json();
        $approval = collect($response['links'] ?? [])->firstWhere('rel', 'payer-action')
            ?? collect($response['links'] ?? [])->firstWhere('rel', 'approve');

        if (! is_array($approval) || blank($approval['href'] ?? null)) {
            throw new RuntimeException('PayPal did not return an approval URL.');
        }

        return ['id' => (string) $response['id'], 'url' => (string) $approval['href']];
    }

    public function retrievePayment(string $orderId): array
    {
        $transaction = PaymentGatewayTransaction::query()->where('provider_session_id', $orderId)->with('church')->firstOrFail();
        $configuration = $this->configuration($transaction->church);
        $capture = $this->authorized($configuration)
            ->withHeaders(['PayPal-Request-Id' => 'capture-'.$transaction->reference])
            ->withBody('{}', 'application/json')
            ->post($this->base($configuration).'/v2/checkout/orders/'.rawurlencode($orderId).'/capture');

        if (! $capture->successful()) {
            $capture = $this->authorized($configuration)->get($this->base($configuration).'/v2/checkout/orders/'.rawurlencode($orderId))->throw();
        }
        $order = $capture->json();
        $captured = data_get($order, 'purchase_units.0.payments.captures.0', []);
        $status = (string) ($captured['status'] ?? $order['status'] ?? 'pending');

        return [
            'session_id' => (string) ($order['id'] ?? $orderId),
            'payment_id' => (string) ($captured['id'] ?? $orderId),
            'status' => $status === 'COMPLETED' ? 'paid' : strtolower($status),
            'amount_minor' => (int) round(((float) data_get($captured, 'amount.value', 0)) * 100),
            'currency' => strtoupper((string) data_get($captured, 'amount.currency_code', 'USD')),
            'paid_at' => CarbonImmutable::parse((string) ($captured['create_time'] ?? $order['update_time'] ?? 'now')),
        ];
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $orderId = (string) (data_get($event, 'resource.supplementary_data.related_ids.order_id')
            ?? data_get($event, 'resource.id', ''));
        $churchId = PaymentGatewayTransaction::query()->where('provider_session_id', $orderId)->value('church_id');
        if (! $churchId && data_get($event, 'resource.custom_id')) {
            $churchId = PaymentGatewayTransaction::query()->where('reference', data_get($event, 'resource.custom_id'))->value('church_id');
        }
        $church = $churchId ? Church::query()->find($churchId) : Church::query()->first();
        $configuration = $this->configuration($church);
        $verification = $this->authorized($configuration)->post($this->base($configuration).'/v1/notifications/verify-webhook-signature', [
            'transmission_id' => $this->header($headers, 'paypal-transmission-id'),
            'transmission_time' => $this->header($headers, 'paypal-transmission-time'),
            'cert_url' => $this->header($headers, 'paypal-cert-url'),
            'auth_algo' => $this->header($headers, 'paypal-auth-algo'),
            'transmission_sig' => $this->header($headers, 'paypal-transmission-sig'),
            'webhook_id' => $configuration['webhook_id'],
            'webhook_event' => $event,
        ])->throw()->json();
        if (($verification['verification_status'] ?? '') !== 'SUCCESS') {
            throw new RuntimeException('PayPal webhook signature verification failed.');
        }

        return [
            'type' => (string) ($event['event_type'] ?? ''),
            'session_id' => $orderId,
        ];
    }

    public function testConnection(Church $church): array
    {
        $configuration = $this->configuration($church);
        $this->accessToken($configuration);

        return ['mode' => $configuration['mode'], 'message' => 'PayPal '.strtoupper($configuration['mode']).' connection verified.'];
    }

    private function authorized(array $configuration): PendingRequest
    {
        return Http::acceptJson()->withToken($this->accessToken($configuration));
    }

    private function accessToken(array $configuration): string
    {
        $response = Http::asForm()->withBasicAuth($configuration['client_id'], $configuration['client_secret'])
            ->post($this->base($configuration).'/v1/oauth2/token', ['grant_type' => 'client_credentials'])->throw()->json();
        $token = (string) ($response['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('PayPal did not return an access token.');
        }

        return $token;
    }

    private function base(array $configuration): string
    {
        return $configuration['mode'] === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function configuration(?Church $church): array
    {
        if (! $this->isConfigured($church)) {
            throw new RuntimeException('PayPal is not configured.');
        }

        return $this->settings->forChurch($church, 'paypal');
    }

    private function header(array $headers, string $key): string
    {
        $value = $headers[$key] ?? '';

        return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
    }
}
