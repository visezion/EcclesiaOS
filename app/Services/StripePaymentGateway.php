<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use App\Models\PaymentGatewayTransaction;
use Carbon\CarbonImmutable;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

final class StripePaymentGateway
{
    public function __construct(private readonly PaymentGatewaySettings $settings) {}

    public function isConfigured(?Church $church = null): bool
    {
        $church ??= Church::query()->first();

        return $this->settings->isConfigured($church, 'stripe');
    }

    public function createCheckout(PaymentGatewayTransaction $transaction, string $successUrl, string $cancelUrl): array
    {
        $configuration = $this->configuration($transaction->church);
        $session = $this->client((string) $configuration['secret_key'])->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'client_reference_id' => $transaction->reference,
            'customer_email' => $transaction->donor_email,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($transaction->currency),
                    'unit_amount' => $transaction->amount_minor,
                    'product_data' => [
                        'name' => 'Gift to '.$transaction->church->name,
                        'description' => $transaction->fund?->name ? 'Fund: '.$transaction->fund->name : 'General giving',
                    ],
                ],
            ]],
            'metadata' => [
                'gateway_transaction_id' => (string) $transaction->id,
                'reference' => $transaction->reference,
                'church_id' => (string) $transaction->church_id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'gateway_transaction_id' => (string) $transaction->id,
                    'reference' => $transaction->reference,
                ],
            ],
        ], ['idempotency_key' => 'checkout-'.$transaction->reference]);

        if (! is_string($session->url) || $session->url === '') {
            throw new RuntimeException('Stripe did not return a checkout URL.');
        }

        return ['id' => $session->id, 'url' => $session->url];
    }

    public function retrievePayment(string $sessionId): array
    {
        $transaction = PaymentGatewayTransaction::query()->where('provider_session_id', $sessionId)->with('church')->firstOrFail();
        $configuration = $this->configuration($transaction->church);
        $session = $this->client((string) $configuration['secret_key'])->checkout->sessions->retrieve($sessionId, [
            'expand' => ['payment_intent.latest_charge'],
        ]);
        $intent = $session->payment_intent;
        $paymentId = is_string($intent) ? $intent : (string) ($intent?->id ?? '');
        $charge = ! is_string($intent) ? $intent?->latest_charge : null;
        $chargeCreated = is_object($charge) ? ($charge->created ?? null) : null;

        return [
            'session_id' => $session->id,
            'payment_id' => $paymentId !== '' ? $paymentId : $session->id,
            'status' => (string) $session->payment_status,
            'amount_minor' => (int) $session->amount_total,
            'currency' => strtoupper((string) $session->currency),
            'paid_at' => CarbonImmutable::createFromTimestampUTC((int) ($chargeCreated ?? $session->created)),
        ];
    }

    public function verifyWebhook(string $payload, string $signature): array
    {
        $secrets = $this->settings->webhookSecrets('stripe');
        if ($secrets === []) {
            throw new RuntimeException('Stripe webhook signing secret is not configured.');
        }

        $event = null;
        foreach ($secrets as $secret) {
            try {
                $event = Webhook::constructEvent($payload, $signature, $secret);
                break;
            } catch (SignatureVerificationException) {
                continue;
            }
        }
        if (! $event) {
            throw new RuntimeException('Stripe webhook signature verification failed.');
        }
        $session = $event->data->object;

        return ['type' => $event->type, 'session_id' => (string) $session->id];
    }

    public function testConnection(Church $church): array
    {
        $configuration = $this->configuration($church);
        $this->client((string) $configuration['secret_key'])->balance->retrieve();

        return [
            'mode' => (string) $configuration['mode'],
            'message' => 'Stripe '.strtoupper((string) $configuration['mode']).' connection verified.',
        ];
    }

    private function client(string $secret): StripeClient
    {
        return new StripeClient($secret);
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(?Church $church): array
    {
        if (! $this->isConfigured($church)) {
            throw new RuntimeException('Stripe is not configured.');
        }

        return $this->settings->forChurch($church, 'stripe');
    }
}
