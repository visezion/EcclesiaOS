<?php

namespace App\Support;

use App\Models\Church;
use Illuminate\Support\Str;
use Throwable;

final class Branding
{
    public function __construct(
        public readonly ?Church $church,
        public readonly array $settings,
    ) {}

    public static function current(): self
    {
        $request = app()->bound('request') ? request() : null;
        $cacheKey = self::class.'.current';

        if ($request?->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }

        try {
            $church = Church::query()->first();
            $branding = new self($church, is_array($church?->settings) ? $church->settings : []);
        } catch (Throwable) {
            // Error and maintenance pages must still render when the database is unavailable.
            $branding = new self(null, []);
        }

        $request?->attributes->set($cacheKey, $branding);

        return $branding;
    }

    public function systemName(): string
    {
        return (string) config('church.product_name', 'EcclesiaOS');
    }

    public function churchName(): string
    {
        return (string) (data_get($this->settings, 'church_name') ?: $this->church?->name ?: config('church.name', config('app.name', 'EcclesiaOS')));
    }

    public function subtitle(): string
    {
        return (string) (data_get($this->settings, 'subtitle') ?: config('church.subtitle', 'Enterprise Church Management System'));
    }

    public function interfaceZoom(): int
    {
        return min(120, max(70, (int) data_get($this->settings, 'interface_zoom', 80)));
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
