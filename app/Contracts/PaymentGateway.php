<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Church;
use App\Models\PaymentGatewayTransaction;

interface PaymentGateway
{
    public function isConfigured(?Church $church = null, string $provider = 'stripe'): bool;

    /**
     * @return array<string, array{label: string, region: string, currency: string, configured: bool}>
     */
    public function providers(?Church $church = null): array;

    public function currency(Church $church, string $provider): string;

    /**
     * @return array{id: string, url: string}
     */
    public function createCheckout(PaymentGatewayTransaction $transaction, string $successUrl, string $cancelUrl): array;

    /**
     * @return array{session_id: string, payment_id: string, status: string, amount_minor: int, currency: string, paid_at: \DateTimeInterface}
     */
    public function retrievePayment(string $sessionId): array;

    /**
     * @return array{type: string, session_id: string}
     */
    public function verifyWebhook(string $payload, string $signature, string $provider = 'stripe', array $headers = []): array;

    /**
     * @return array{mode: string, message: string}
     */
    public function testConnection(Church $church, string $provider = 'stripe'): array;
}
