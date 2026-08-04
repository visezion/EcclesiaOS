<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Church;
use App\Models\PaymentGatewayTransaction;
use InvalidArgumentException;

final class PaymentGatewayManager implements PaymentGateway
{
    public function __construct(
        private readonly StripePaymentGateway $stripe,
        private readonly PaystackPaymentGateway $paystack,
        private readonly PayPalPaymentGateway $paypal,
        private readonly PaymentGatewaySettings $settings,
    ) {}

    public function isConfigured(?Church $church = null, string $provider = 'stripe'): bool
    {
        return $this->driver($provider)->isConfigured($church);
    }

    public function providers(?Church $church = null): array
    {
        $church ??= Church::query()->first();

        return collect([
            'stripe' => ['label' => 'Stripe', 'region' => 'Global', 'currency' => strtoupper((string) ($church?->currency ?: 'USD'))],
            'paystack' => ['label' => 'Paystack', 'region' => 'Nigeria', 'currency' => 'NGN'],
            'paypal' => ['label' => 'PayPal', 'region' => 'United States', 'currency' => 'USD'],
        ])->map(fn (array $meta, string $key): array => $meta + ['configured' => $this->isConfigured($church, $key)])->all();
    }

    public function currency(Church $church, string $provider): string
    {
        if ($provider === 'stripe') {
            return strtoupper((string) $church->currency);
        }

        return strtoupper((string) $this->settings->forChurch($church, $provider)['currency']);
    }

    public function createCheckout(PaymentGatewayTransaction $transaction, string $successUrl, string $cancelUrl): array
    {
        return $this->driver($transaction->provider)->createCheckout($transaction, $successUrl, $cancelUrl);
    }

    public function retrievePayment(string $sessionId): array
    {
        $provider = PaymentGatewayTransaction::query()->where('provider_session_id', $sessionId)->value('provider');

        return $this->driver((string) $provider)->retrievePayment($sessionId);
    }

    public function verifyWebhook(string $payload, string $signature, string $provider = 'stripe', array $headers = []): array
    {
        return $provider === 'paypal'
            ? $this->paypal->verifyWebhook($payload, array_change_key_case($headers))
            : $this->driver($provider)->verifyWebhook($payload, $signature);
    }

    public function testConnection(Church $church, string $provider = 'stripe'): array
    {
        return $this->driver($provider)->testConnection($church);
    }

    private function driver(string $provider): object
    {
        return match ($provider) {
            'stripe' => $this->stripe,
            'paystack' => $this->paystack,
            'paypal' => $this->paypal,
            default => throw new InvalidArgumentException('Unsupported payment provider.'),
        };
    }
}
