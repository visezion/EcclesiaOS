<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use App\Models\PaymentGatewayTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PaystackPaymentGateway
{
    private const API = 'https://api.paystack.co';

    public function __construct(private readonly PaymentGatewaySettings $settings) {}

    public function isConfigured(?Church $church = null): bool
    {
        return $this->settings->isConfigured($church ?? Church::query()->first(), 'paystack');
    }

    public function createCheckout(PaymentGatewayTransaction $transaction, string $successUrl, string $cancelUrl): array
    {
        $configuration = $this->configuration($transaction->church);
        $response = Http::acceptJson()->withToken($configuration['secret_key'])
            ->post(self::API.'/transaction/initialize', [
                'email' => $transaction->donor_email,
                'amount' => $transaction->amount_minor,
                'currency' => $transaction->currency,
                'reference' => $transaction->reference,
                'callback_url' => $successUrl,
                'metadata' => [
                    'gateway_transaction_id' => $transaction->id,
                    'church_id' => $transaction->church_id,
                    'cancel_action' => $cancelUrl,
                ],
            ])->throw()->json();

        $url = data_get($response, 'data.authorization_url');
        if (($response['status'] ?? false) !== true || ! is_string($url) || $url === '') {
            throw new RuntimeException('Paystack did not return a checkout URL.');
        }

        return ['id' => (string) data_get($response, 'data.reference', $transaction->reference), 'url' => $url];
    }

    public function retrievePayment(string $reference): array
    {
        $transaction = PaymentGatewayTransaction::query()->where('provider_session_id', $reference)->with('church')->firstOrFail();
        $configuration = $this->configuration($transaction->church);
        $response = Http::acceptJson()->withToken($configuration['secret_key'])
            ->get(self::API.'/transaction/verify/'.rawurlencode($reference))->throw()->json();
        $data = (array) data_get($response, 'data', []);

        return [
            'session_id' => (string) ($data['reference'] ?? $reference),
            'payment_id' => (string) ($data['id'] ?? $reference),
            'status' => ($data['status'] ?? '') === 'success' ? 'paid' : (string) ($data['status'] ?? 'pending'),
            'amount_minor' => (int) ($data['amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? 'NGN')),
            'paid_at' => CarbonImmutable::parse((string) ($data['paid_at'] ?? $data['created_at'] ?? 'now')),
        ];
    }

    public function verifyWebhook(string $payload, string $signature): array
    {
        $valid = false;
        foreach ($this->settings->webhookSecrets('paystack') as $secret) {
            if (hash_equals(hash_hmac('sha512', $payload, $secret), $signature)) {
                $valid = true;
                break;
            }
        }
        if (! $valid) {
            throw new RuntimeException('Paystack webhook signature verification failed.');
        }
        $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

        return [
            'type' => (string) ($event['event'] ?? ''),
            'session_id' => (string) data_get($event, 'data.reference', ''),
        ];
    }

    public function testConnection(Church $church): array
    {
        $configuration = $this->configuration($church);
        Http::acceptJson()->withToken($configuration['secret_key'])->get(self::API.'/balance')->throw();

        return ['mode' => $configuration['mode'], 'message' => 'Paystack '.strtoupper($configuration['mode']).' connection verified.'];
    }

    private function configuration(?Church $church): array
    {
        if (! $this->isConfigured($church)) {
            throw new RuntimeException('Paystack is not configured.');
        }

        return $this->settings->forChurch($church, 'paystack');
    }
}
