<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\CommunicationProviderSetting;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class EmailProviderManager
{
    /**
     * Configure the enabled email integration for a notification send.
     *
     * @return array<string, mixed>
     */
    public function configureFor(?int $churchId): array
    {
        $snapshot = [
            'mail.default' => config('mail.default'),
            'mail.mailers.communication_provider' => config('mail.mailers.communication_provider'),
            'mail.from.address' => config('mail.from.address'),
            'mail.from.name' => config('mail.from.name'),
        ];
        $setting = $this->forChurch($churchId);
        $transport = $this->transport($setting);

        if ($transport !== null) {
            $this->purgeMailer('communication_provider');
            config([
                'mail.default' => 'communication_provider',
                'mail.mailers.communication_provider' => $transport,
            ]);

            $senderEmail = trim((string) data_get($setting?->settings, 'sender_number', ''));
            $senderIdentity = trim((string) ($setting?->sender_identity ?? ''));
            if ($senderEmail !== '') {
                config(['mail.from.address' => $senderEmail]);
            }
            if ($senderIdentity !== '') {
                config(['mail.from.name' => $senderIdentity]);
            }
        }

        return compact('snapshot', 'setting', 'transport');
    }

    /** @param array<string, mixed> $state */
    public function restore(array $state): void
    {
        config($state['snapshot'] ?? []);
        $this->purgeMailer('communication_provider');
    }

    public function forChurch(?int $churchId): ?CommunicationProviderSetting
    {
        return CommunicationProviderSetting::query()
            ->where('channel', 'email')
            ->where('enabled', true)
            ->when($churchId !== null, fn ($query) => $query->orderByRaw('case when church_id = ? then 0 else 1 end', [$churchId]))
            ->first();
    }

    public function mailerFor(?int $churchId): mixed
    {
        $setting = $this->forChurch($churchId);
        $transport = $this->transport($setting);

        if ($transport === null) {
            return null;
        }

        return Mail::build($transport);
    }

    /** @return array<string, mixed>|null */
    public function transport(?CommunicationProviderSetting $setting): ?array
    {
        if (! $setting?->enabled) {
            return null;
        }

        $settings = $setting->settings ?? [];
        $provider = Str::lower((string) $setting->provider);
        $password = $this->apiKey($setting);
        if ($password === null) {
            return null;
        }

        $host = trim((string) ($settings['endpoint_url'] ?? ''));
        $username = trim((string) ($settings['account_id'] ?? ''));
        $port = (int) ($settings['device_id'] ?? 587);

        if (Str::contains($provider, 'sendgrid')) {
            $host = 'smtp.sendgrid.net';
            $username = 'apikey';
            $port = 587;
        } elseif (Str::contains($provider, 'mailgun')) {
            $endpoint = Str::lower((string) ($settings['endpoint_url'] ?? ''));
            $host = Str::contains($endpoint, 'eu.mailgun') ? 'smtp.eu.mailgun.org' : 'smtp.mailgun.org';
            $username = 'postmaster@'.trim((string) ($settings['account_id'] ?? ''));
            $port = 587;
        }

        if ($host === '' || $username === '' || $port < 1 || $port > 65535) {
            return null;
        }

        return [
            'transport' => 'smtp',
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'scheme' => $port === 465 ? 'smtps' : 'smtp',
            'timeout' => 20,
        ];
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

    private function purgeMailer(string $name): void
    {
        if (Mail::getFacadeRoot() instanceof MailManager) {
            Mail::purge($name);
        }
    }
}
