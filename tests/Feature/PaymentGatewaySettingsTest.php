<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Church;
use App\Models\PaymentGatewayTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class PaymentGatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_save_encrypted_stripe_credentials_from_gui(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('payment-gateways.update'), [
                'enabled' => '1',
                'mode' => 'test',
                'publishable_key' => 'pk_test_gui_public_1234',
                'secret_key' => 'sk_test_gui_secret_5678',
                'webhook_secret' => 'whsec_gui_webhook_9012',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $setting = Setting::query()->where('key', 'payment_gateway.stripe')->firstOrFail();
        $this->assertSame('pk_test_gui_public_1234', $setting->value['publishable_key']);
        $this->assertSame('sk_test_gui_secret_5678', Crypt::decryptString($setting->value['secret_key_encrypted']));
        $this->assertSame('whsec_gui_webhook_9012', Crypt::decryptString($setting->value['webhook_secret_encrypted']));
        $this->assertStringNotContainsString('sk_test_gui_secret_5678', (string) $setting->getRawOriginal('value'));

        $this->actingAs($admin)
            ->get(route('payment-gateways.index'))
            ->assertOk()
            ->assertSee('Payment Gateways')
            ->assertSee('Encrypted key ending 5678')
            ->assertDontSee('sk_test_gui_secret_5678');
    }

    public function test_blank_secret_fields_preserve_saved_credentials(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $payload = [
            'enabled' => '1',
            'mode' => 'test',
            'publishable_key' => 'pk_test_gui_public_1234',
            'secret_key' => 'sk_test_gui_secret_5678',
            'webhook_secret' => 'whsec_gui_webhook_9012',
        ];
        $this->actingAs($admin)->put(route('payment-gateways.update'), $payload);

        $this->actingAs($admin)->put(route('payment-gateways.update'), [
            'enabled' => '1',
            'mode' => 'test',
            'publishable_key' => '',
            'secret_key' => '',
            'webhook_secret' => '',
        ])->assertSessionHasNoErrors();

        $value = Setting::query()->where('key', 'payment_gateway.stripe')->firstOrFail()->value;
        $this->assertSame('sk_test_gui_secret_5678', Crypt::decryptString($value['secret_key_encrypted']));
        $this->assertSame('whsec_gui_webhook_9012', Crypt::decryptString($value['webhook_secret_encrypted']));
    }

    public function test_gui_rejects_test_and_live_key_mismatch(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('payment-gateways.index'))
            ->put(route('payment-gateways.update'), [
                'enabled' => '1',
                'mode' => 'live',
                'publishable_key' => 'pk_test_wrong_mode',
                'secret_key' => 'sk_test_wrong_mode',
                'webhook_secret' => 'whsec_valid',
            ])
            ->assertRedirect(route('payment-gateways.index'))
            ->assertSessionHasErrors(['publishable_key', 'secret_key']);
    }

    public function test_saved_connection_can_be_tested_from_gui(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $this->app->instance(PaymentGateway::class, new GuiFakePaymentGateway);

        $this->actingAs($admin)
            ->post(route('payment-gateways.test'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Stripe TEST connection verified.');

        $this->assertSame(
            'success',
            data_get(Setting::query()->where('key', 'payment_gateway.stripe')->firstOrFail()->value, 'last_test_status'),
        );
    }

    public function test_administrator_can_save_paystack_and_paypal_credentials_from_gui(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)->put(route('payment-gateways.update-provider', 'paystack'), [
            'enabled' => '1',
            'mode' => 'test',
            'currency' => 'NGN',
            'publishable_key' => 'pk_test_paystack_public',
            'secret_key' => 'sk_test_paystack_secret',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->put(route('payment-gateways.update-provider', 'paypal'), [
            'enabled' => '1',
            'mode' => 'test',
            'currency' => 'USD',
            'client_id' => 'paypal-sandbox-client',
            'client_secret' => 'paypal-sandbox-secret',
            'webhook_id' => 'WH-SANDBOX-ID',
        ])->assertSessionHasNoErrors();

        $paystack = Setting::query()->where('key', 'payment_gateway.paystack')->firstOrFail()->value;
        $paypal = Setting::query()->where('key', 'payment_gateway.paypal')->firstOrFail()->value;

        $this->assertSame('sk_test_paystack_secret', Crypt::decryptString($paystack['secret_key_encrypted']));
        $this->assertSame('paypal-sandbox-secret', Crypt::decryptString($paypal['client_secret_encrypted']));
        $this->assertStringNotContainsString('paypal-sandbox-secret', (string) Setting::query()->where('key', 'payment_gateway.paypal')->first()->getRawOriginal('value'));
    }
}

final class GuiFakePaymentGateway implements PaymentGateway
{
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
        return ['id' => 'cs_test', 'url' => 'https://checkout.stripe.test'];
    }

    public function retrievePayment(string $sessionId): array
    {
        throw new \LogicException('Not used.');
    }

    public function verifyWebhook(string $payload, string $signature, string $provider = 'stripe', array $headers = []): array
    {
        throw new \LogicException('Not used.');
    }

    public function testConnection(Church $church, string $provider = 'stripe'): array
    {
        return ['mode' => 'test', 'message' => 'Stripe TEST connection verified.'];
    }
}
