<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Church;
use App\Models\PaymentGatewayTransaction;
use App\Services\PaymentGatewaySettings;
use App\Services\PayPalPaymentGateway;
use App\Services\PaystackPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PaymentProviderSandboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_paystack_sandbox_checkout_and_verification_preserve_ngn_amount_and_date(): void
    {
        $church = Church::factory()->create(['currency' => 'USD']);
        app(PaymentGatewaySettings::class)->save($church, [
            'enabled' => true,
            'mode' => 'test',
            'currency' => 'NGN',
            'publishable_key' => 'pk_test_paystack_1234',
            'secret_key' => 'sk_test_paystack_5678',
        ], 'paystack');
        $transaction = $this->transaction($church, 'paystack', 'NGN', 1257500, 'PAY-PAYSTACK');

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.test/abc', 'reference' => 'PAY-PAYSTACK'],
            ]),
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 78421,
                    'reference' => 'PAY-PAYSTACK',
                    'status' => 'success',
                    'amount' => 1257500,
                    'currency' => 'NGN',
                    'paid_at' => '2026-08-04T15:10:00Z',
                ],
            ]),
        ]);

        $gateway = app(PaystackPaymentGateway::class);
        $checkout = $gateway->createCheckout($transaction->load('church'), 'https://test/callback', 'https://test/cancel');
        $transaction->update(['provider_session_id' => $checkout['id']]);
        $payment = $gateway->retrievePayment($checkout['id']);

        $this->assertSame('https://checkout.paystack.test/abc', $checkout['url']);
        $this->assertSame('paid', $payment['status']);
        $this->assertSame(1257500, $payment['amount_minor']);
        $this->assertSame('NGN', $payment['currency']);
        $this->assertSame('2026-08-04 15:10:00', $payment['paid_at']->utc()->format('Y-m-d H:i:s'));
    }

    public function test_paypal_sandbox_order_and_capture_preserve_usd_amount_and_date(): void
    {
        $church = Church::factory()->create(['currency' => 'NGN']);
        app(PaymentGatewaySettings::class)->save($church, [
            'enabled' => true,
            'mode' => 'test',
            'currency' => 'USD',
            'client_id' => 'paypal-sandbox-client',
            'client_secret' => 'paypal-sandbox-secret',
            'webhook_id' => 'WH-SANDBOX',
        ], 'paypal');
        $transaction = $this->transaction($church, 'paypal', 'USD', 12575, 'PAY-PAYPAL');

        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/v1/oauth2/token')) {
                return Http::response(['access_token' => 'sandbox-access-token']);
            }
            if (str_ends_with($request->url(), '/v2/checkout/orders')) {
                return Http::response([
                    'id' => 'ORDER-SANDBOX',
                    'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve']],
                ], 201);
            }

            return Http::response([
                'id' => 'ORDER-SANDBOX',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'payments' => ['captures' => [[
                        'id' => 'CAPTURE-SANDBOX',
                        'status' => 'COMPLETED',
                        'amount' => ['value' => '125.75', 'currency_code' => 'USD'],
                        'create_time' => '2026-08-04T16:20:30Z',
                    ]]],
                ]],
            ], 201);
        });

        $gateway = app(PayPalPaymentGateway::class);
        $checkout = $gateway->createCheckout($transaction->load('church'), 'https://test/return', 'https://test/cancel');
        $transaction->update(['provider_session_id' => $checkout['id']]);
        $payment = $gateway->retrievePayment($checkout['id']);

        $this->assertSame('https://paypal.test/approve', $checkout['url']);
        $this->assertSame('paid', $payment['status']);
        $this->assertSame(12575, $payment['amount_minor']);
        $this->assertSame('USD', $payment['currency']);
        $this->assertSame('2026-08-04 16:20:30', $payment['paid_at']->utc()->format('Y-m-d H:i:s'));
    }

    private function transaction(Church $church, string $provider, string $currency, int $amountMinor, string $reference): PaymentGatewayTransaction
    {
        return PaymentGatewayTransaction::query()->create([
            'church_id' => $church->id,
            'provider' => $provider,
            'reference' => $reference,
            'status' => 'initiated',
            'amount' => $amountMinor / 100,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'donor_email' => 'sandbox@example.test',
        ]);
    }
}
