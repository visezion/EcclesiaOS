<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Models\SystemUpdate;
use App\Models\User;
use App\Notifications\SystemUpdateAvailableNotification;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

final class UpdateManager
{
    public function __construct(
        private readonly GitHubReleaseService $github,
        private readonly UpdateEnvironment $environment,
    ) {}

    public function check(bool $force = false): ?SystemUpdate
    {
        if (! config('updater.enabled')) {
            throw new RuntimeException('Application update checks are disabled.');
        }

        $cacheKey = 'system-updates.github.'.sha1((string) config('updater.repository').':'.config('updater.channel'));
        if ($force) {
            Cache::forget($cacheKey);
        }

        $release = Cache::remember(
            $cacheKey,
            now()->addSeconds((int) config('updater.check_ttl_seconds')),
            fn (): array => $this->github->latest(),
        );

        if (! version_compare((string) $release['version'], $this->currentVersion(), '>')) {
            Cache::forget('system-updates.available');

            return null;
        }

        $update = SystemUpdate::query()->firstOrNew(['tag' => $release['tag']]);
        $isNew = ! $update->exists;
        $currentStatus = $update->status;
        $update->fill([
            'version' => $release['version'],
            'name' => $release['name'],
            'current_version' => $this->currentVersion(),
            'release_url' => $release['release_url'],
            'asset_name' => $release['asset_name'],
            'asset_api_url' => $release['asset_api_url'],
            'asset_download_url' => $release['asset_download_url'],
            'asset_digest' => $release['asset_digest'],
            'asset_size' => $release['asset_size'],
            'changelog' => $release['changelog'],
            'detected_at' => $update->detected_at ?? now(),
            'metadata' => array_merge($update->metadata ?? [], [
                'published_at' => $release['published_at'],
                'immutable' => $release['immutable'],
                'manifest' => $release['manifest'],
                'installable' => $release['installable'],
            ]),
        ]);
        $update->status = $isNew ? 'detected' : $currentStatus;
        $update->save();

        Cache::put('system-updates.available', $update, now()->addMinute());

        if ($isNew) {
            User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'Super Administrator'))
                ->each(fn (User $user) => $user->notify(new SystemUpdateAvailableNotification($update)));
        }

        return $update;
    }

    public function available(): ?SystemUpdate
    {
        try {
            $cached = Cache::get('system-updates.available');
            $update = $cached instanceof SystemUpdate
                ? $cached
                : (is_numeric($cached) ? SystemUpdate::query()->find((int) $cached) : null);

            if (! $update?->isAvailable()) {
                $update = SystemUpdate::query()
                    ->whereNotIn('status', ['completed', 'rolled_back', 'skipped'])
                    ->latest('detected_at')
                    ->get()
                    ->first(fn (SystemUpdate $candidate): bool => $candidate->isAvailable());
            }

            if ($update) {
                Cache::put('system-updates.available', $update, now()->addMinute());
            }

            return $update;
        } catch (Throwable) {
            return null;
        }
    }

    public function approve(SystemUpdate $update, User $user): SystemUpdate
    {
        if (! $update->isAvailable() || ! in_array($update->status, ['detected', 'failed'], true)) {
            throw new RuntimeException('This update is no longer available.');
        }

        $diagnostics = $this->environment->diagnostics();
        if (! $diagnostics['ready']) {
            throw new RuntimeException('The server is not configured for safe one-click installation.');
        }

        $manifest = data_get($update->metadata, 'manifest', []);
        $minimumVersion = (string) data_get($manifest, 'minimum_version', '0.0.0');
        $minimumPhp = (string) data_get($manifest, 'minimum_php', '8.2.0');

        if (version_compare($this->currentVersion(), $minimumVersion, '<')) {
            throw new RuntimeException("Version {$update->version} requires at least application version {$minimumVersion}.");
        }

        if (version_compare(PHP_VERSION, $minimumPhp, '<')) {
            throw new RuntimeException("Version {$update->version} requires PHP {$minimumPhp} or newer.");
        }

        if (! $update->asset_name || ! $update->asset_download_url) {
            throw new RuntimeException("Version {$update->version} was detected, but the release does not include a packaged update.");
        }

        $update->forceFill([
            'status' => 'pending',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'error' => null,
        ])->save();

        Cache::put('system-updates.available', $update->fresh(), now()->addMinute());

        return $update;
    }

    public function skip(SystemUpdate $update): void
    {
        if (! $update->isAvailable() || ! in_array($update->status, ['detected', 'failed'], true)) {
            throw new RuntimeException('This update can no longer be dismissed.');
        }

        $update->forceFill(['status' => 'skipped'])->save();
        Cache::forget('system-updates.available');
    }

    public function currentVersion(): string
    {
        return ltrim((string) config('updater.current_version', '0.0.0'), 'vV');
    }
}
