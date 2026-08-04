<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

final class PaymentGatewaySettings
{
    public const PROVIDERS = ['stripe', 'paystack', 'paypal'];

    /**
     * @return array<string, mixed>
     */
    public function forChurch(?Church $church, string $provider = 'stripe'): array
    {
        $this->assertProvider($provider);
        $stored = $church ? Setting::query()->where('church_id', $church->id)->where('key', $this->key($provider))->value('value') : null;
        $stored = is_array($stored) ? $stored : [];
        $fallback = (array) config('services.'.$provider, []);
        $secret = $this->decrypt($stored['secret_key_encrypted'] ?? null) ?: (string) ($fallback['secret'] ?? '');
        $webhookSecret = $this->decrypt($stored['webhook_secret_encrypted'] ?? null) ?: (string) ($fallback['webhook_secret'] ?? '');
        $publishableKey = (string) ($stored['publishable_key'] ?? ($fallback['key'] ?? ''));
        $clientId = (string) ($stored['client_id'] ?? ($fallback['client_id'] ?? ''));
        $clientSecret = $this->decrypt($stored['client_secret_encrypted'] ?? null) ?: (string) ($fallback['client_secret'] ?? '');
        $webhookId = (string) ($stored['webhook_id'] ?? ($fallback['webhook_id'] ?? ''));

        return [
            'provider' => $provider,
            'enabled' => (bool) ($stored['enabled'] ?? ($provider === 'stripe')),
            'mode' => (string) ($stored['mode'] ?? ($this->looksLive($secret) ? 'live' : 'test')),
            'currency' => strtoupper((string) ($stored['currency'] ?? ($provider === 'paystack' ? 'NGN' : ($provider === 'paypal' ? 'USD' : '')))),
            'publishable_key' => $publishableKey,
            'secret_key' => $secret,
            'webhook_secret' => $webhookSecret,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'webhook_id' => $webhookId,
            'publishable_last_four' => $stored['publishable_last_four'] ?? ($publishableKey !== '' ? Str::substr($publishableKey, -4) : null),
            'secret_last_four' => $stored['secret_last_four'] ?? ($secret !== '' ? Str::substr($secret, -4) : null),
            'webhook_last_four' => $stored['webhook_last_four'] ?? ($webhookSecret !== '' ? Str::substr($webhookSecret, -4) : null),
            'client_id_last_four' => $stored['client_id_last_four'] ?? ($clientId !== '' ? Str::substr($clientId, -4) : null),
            'client_secret_last_four' => $stored['client_secret_last_four'] ?? ($clientSecret !== '' ? Str::substr($clientSecret, -4) : null),
            'webhook_id_last_four' => $stored['webhook_id_last_four'] ?? ($webhookId !== '' ? Str::substr($webhookId, -4) : null),
            'last_tested_at' => $stored['last_tested_at'] ?? null,
            'last_test_status' => $stored['last_test_status'] ?? null,
            'last_test_message' => $stored['last_test_message'] ?? null,
        ];
    }

    public function isConfigured(?Church $church, string $provider = 'stripe'): bool
    {
        $settings = $this->forChurch($church, $provider);

        return (bool) $settings['enabled'] && match ($provider) {
            'stripe' => str_starts_with((string) $settings['secret_key'], 'sk_'),
            'paystack' => str_starts_with((string) $settings['secret_key'], 'sk_'),
            'paypal' => filled($settings['client_id']) && filled($settings['client_secret']),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(Church $church, array $input, string $provider = 'stripe'): void
    {
        $this->assertProvider($provider);
        $current = $this->raw($church, $provider);

        $value = array_merge($current, [
            'enabled' => (bool) ($input['enabled'] ?? false),
            'mode' => (string) $input['mode'],
            'currency' => strtoupper((string) ($input['currency'] ?? ($provider === 'paystack' ? 'NGN' : 'USD'))),
        ]);

        foreach (['publishable_key', 'client_id', 'webhook_id'] as $plainField) {
            if (filled($input[$plainField] ?? null)) {
                $plain = trim((string) $input[$plainField]);
                $value[$plainField] = $plain;
                $value[$plainField.'_last_four'] = Str::substr($plain, -4);
            }
        }
        foreach (['secret_key', 'webhook_secret', 'client_secret'] as $secretField) {
            if (filled($input[$secretField] ?? null)) {
                $secret = trim((string) $input[$secretField]);
                $value[$secretField.'_encrypted'] = Crypt::encryptString($secret);
                $value[$secretField.'_last_four'] = Str::substr($secret, -4);
                if ($secretField === 'secret_key') {
                    $value['secret_last_four'] = Str::substr($secret, -4);
                }
                if ($secretField === 'webhook_secret') {
                    $value['webhook_last_four'] = Str::substr($secret, -4);
                }
            }
        }

        Setting::query()->updateOrCreate(
            ['church_id' => $church->id, 'key' => $this->key($provider)],
            ['value' => $value, 'type' => 'encrypted_integration'],
        );
    }

    public function recordTest(Church $church, bool $success, string $message, string $provider = 'stripe'): void
    {
        $value = $this->raw($church, $provider);
        $value['last_tested_at'] = now()->toIso8601String();
        $value['last_test_status'] = $success ? 'success' : 'failed';
        $value['last_test_message'] = $message;

        Setting::query()->updateOrCreate(
            ['church_id' => $church->id, 'key' => $this->key($provider)],
            ['value' => $value, 'type' => 'encrypted_integration'],
        );
    }

    /**
     * @return array<int, string>
     */
    public function webhookSecrets(string $provider = 'stripe'): array
    {
        $secrets = Setting::query()
            ->where('key', $this->key($provider))
            ->get()
            ->map(fn (Setting $setting): string => $this->decrypt(data_get(
                $setting->value,
                $provider === 'paystack' ? 'secret_key_encrypted' : 'webhook_secret_encrypted'
            )))
            ->filter()
            ->values()
            ->all();

        $fallback = $provider === 'paystack' ? config('services.paystack.secret') : config('services.'.$provider.'.webhook_secret');
        if (filled($fallback)) {
            $secrets[] = (string) $fallback;
        }

        return array_values(array_unique($secrets));
    }

    /**
     * @return array<string, mixed>
     */
    private function raw(Church $church, string $provider): array
    {
        $value = Setting::query()->where('church_id', $church->id)->where('key', $this->key($provider))->value('value');

        return is_array($value) ? $value : [];
    }

    private function decrypt(mixed $encrypted): string
    {
        if (! is_string($encrypted) || $encrypted === '') {
            return '';
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return '';
        }
    }

    private function key(string $provider): string
    {
        return 'payment_gateway.'.$provider;
    }

    private function assertProvider(string $provider): void
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new \InvalidArgumentException('Unsupported payment provider.');
        }
    }

    private function looksLive(string $secret): bool
    {
        return str_contains($secret, '_live_');
    }
}
