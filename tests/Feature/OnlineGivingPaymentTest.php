<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Fund;
use App\Models\PaymentGatewayTransaction;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OnlineGivingPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_giving_page_is_available_and_explains_unconfigured_gateway(): void
    {
        Church::factory()->create(['name' => 'Grace Church', 'currency' => 'USD']);

        $this->get(route('giving.create'))
            ->assertOk()
            ->assertSee('Secure online giving')
            ->assertSee('Online giving is being configured');
    }

    public function test_verified_sandbox_payment_records_exact_amount_currency_and_payment_date_once(): void
    {
        $church = Church::factory()->create(['currency' => 'USD']);
        $campus = Campus::factory()->for($church)->create();
        $fund = Fund::query()->create(['church_id' => $church->id, 'name' => 'Missions', 'is_active' => true]);
        $paidAt = new DateTimeImmutable('2026-08-04 14:25:30 UTC');
        $gateway = new FakePaymentGateway([
            'session_id' => 'cs_test_ecclesia',
            'payment_id' => 'pi_test_ecclesia',
            'status' => 'paid',
            'amount_minor' => 12575,
            'currency' => 'USD',
            'paid_at' => $paidAt,
        ]);
        $this->app->instance(PaymentGateway::class, $gateway);

        $this->post(route('giving.checkout'), [
            'amount' => '125.75',
            'fund_id' => $fund->id,
            'campus_id' => $campus->id,
            'donor_name' => 'Sandbox Donor',
            'donor_email' => 'sandbox@example.test',
        ])->assertRedirect('https://checkout.stripe.test/session');

        $transaction = PaymentGatewayTransaction::query()->sole();
        $this->assertSame('125.75', $transaction->amount);
        $this->assertSame(12575, $transaction->amount_minor);
        $this->assertSame('2026-08-04', $transaction->created_at->toDateString());
        $this->assertSame('cs_test_ecclesia', $transaction->provider_session_id);

        $this->get(route('giving.success', ['session_id' => 'cs_test_ecclesia']))
            ->assertOk()
            ->assertSee('Payment confirmed')
            ->assertSee('USD 125.75')
            ->assertSee('August 04, 2026');

        $this->get(route('giving.success', ['session_id' => 'cs_test_ecclesia']))->assertOk();

        $donation = Donation::query()->sole();
        $this->assertSame('125.75', $donation->amount);
        $this->assertSame('USD', $donation->currency);
        $this->assertSame('online', $donation->method);
        $this->assertSame('2026-08-04 14:25:30', $donation->received_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($fund->id, $donation->fund_id);
        $this->assertSame($campus->id, $donation->campus_id);
        $this->assertDatabaseCount('donations', 1);
        $this->assertDatabaseHas('payment_gateway_transactions', [
            'id' => $transaction->id,
            'status' => 'paid',
            'donation_id' => $donation->id,
            'provider_payment_id' => 'pi_test_ecclesia',
        ]);
    }

    public function test_amount_mismatch_never_records_a_donation(): void
    {
        $church = Church::factory()->create(['currency' => 'USD']);
        $gateway = new FakePaymentGateway([
            'session_id' => 'cs_test_mismatch',
            'payment_id' => 'pi_test_mismatch',
            'status' => 'paid',
            'amount_minor' => 9900,
            'currency' => 'USD',
            'paid_at' => new DateTimeImmutable('2026-08-04 12:00:00 UTC'),
        ], 'cs_test_mismatch');
        $this->app->instance(PaymentGateway::class, $gateway);

        $this->post(route('giving.checkout'), [
            'amount' => '100.00',
            'donor_name' => 'Mismatch Test',
            'donor_email' => 'mismatch@example.test',
        ]);

        $this->get(route('giving.success', ['session_id' => 'cs_test_mismatch']))->assertOk();

        $this->assertDatabaseCount('donations', 0);
        $this->assertDatabaseHas('payment_gateway_transactions', ['status' => 'amount_mismatch']);
    }

    public function test_signed_webhook_flow_is_idempotent(): void
    {
        $church = Church::factory()->create(['currency' => 'USD']);
        $gateway = new FakePaymentGateway([
            'session_id' => 'cs_test_webhook',
            'payment_id' => 'pi_test_webhook',
            'status' => 'paid',
            'amount_minor' => 5000,
            'currency' => 'USD',
            'paid_at' => new DateTimeImmutable('2026-08-04 15:00:00 UTC'),
        ], 'cs_test_webhook');
        $this->app->instance(PaymentGateway::class, $gateway);
        PaymentGatewayTransaction::query()->create([
            'church_id' => $church->id,
            'provider' => 'stripe',
            'provider_session_id' => 'cs_test_webhook',
            'reference' => 'PAY-WEBHOOK-TEST',
            'status' => 'initiated',
            'amount' => 50,
            'amount_minor' => 5000,
            'currency' => 'USD',
        ]);

        $this->postJson(route('webhooks.stripe'), ['id' => 'evt_test'], ['Stripe-Signature' => 'signed'])
            ->assertOk()
            ->assertJson(['received' => true]);
        $this->postJson(route('webhooks.stripe'), ['id' => 'evt_test'], ['Stripe-Signature' => 'signed'])->assertOk();

        $this->assertDatabaseCount('donations', 1);
    }
}

final class FakePaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly array $payment = [],
        private readonly string $sessionId = 'cs_test_ecclesia',
    ) {}

    public function isConfigured(?Church $church = null, string $provider = 'stripe'): bool
    {
        return true;
    }

    public function providers(?Church $church = null): array
    {
        return ['stripe' => ['label' => 'Stripe', 'region' => 'Global', 'currency' => 'USD', 'configured' => true]];
    }

    public function currency(Church $church, string $provider): string
    {
        return strtoupper((string) $church->currency);
    }

    public function createCheckout(PaymentGatewayTransaction $transaction, string $successUrl, string $cancelUrl): array
    {
        return ['id' => $this->sessionId, 'url' => 'https://checkout.stripe.test/session'];
    }

    public function retrievePayment(string $sessionId): array
    {
        return $this->payment;
    }

    public function verifyWebhook(string $payload, string $signature, string $provider = 'stripe', array $headers = []): array
    {
        return ['type' => 'checkout.session.completed', 'session_id' => $this->sessionId];
    }

    public function testConnection(Church $church, string $provider = 'stripe'): array
    {
        return ['mode' => 'test', 'message' => 'Stripe TEST connection verified.'];
    }
}
