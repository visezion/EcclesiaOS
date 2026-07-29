<?php

namespace App\Support;

use App\Models\Church;
use Illuminate\Support\Str;

final class Branding
{
    public function __construct(
        public readonly Church $church,
        public readonly array $settings,
    ) {
    }

    public static function current(): self
    {
        $church = Church::query()->first();

        if (! $church) {
            $church = Church::query()->firstOrCreate(
                ['slug' => 'kingdom-life-global-church'],
                [
                    'name' => config('church.name'),
                    'timezone' => config('church.timezone'),
                    'currency' => config('church.currency'),
                    'email' => config('church.contact_email'),
                    'phone' => config('church.contact_phone'),
                    'address' => config('church.address'),
                    'settings' => [],
                ],
            );
        }

        return new self($church, $church->settings ?? []);
    }

    public function systemName(): string
    {
        return (string) (data_get($this->settings, 'system_name') ?: config('app.name', 'EcclesiaOS'));
    }

    public function churchName(): string
    {
        return (string) (data_get($this->settings, 'church_name') ?: $this->church->name ?: config('church.name', config('app.name', 'EcclesiaOS')));
    }

    public function subtitle(): string
    {
        return (string) (data_get($this->settings, 'subtitle') ?: config('church.subtitle', 'Enterprise Church Management System'));
    }

    public function logo(): ?string
    {
        return $this->assetPath(data_get($this->settings, 'logo') ?: config('church.logo'));
    }

    public function sidebarBackground(): ?string
    {
        return $this->assetPath(data_get($this->settings, 'sidebar_background') ?: config('church.sidebar_background'));
    }

    public function assetPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://', '/'])
            ? $path
            : (Str::startsWith($path, 'branding/') ? asset('storage/'.$path) : asset($path));
    }
}
