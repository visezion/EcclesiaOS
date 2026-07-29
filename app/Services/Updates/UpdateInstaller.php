<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Models\SystemUpdate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class UpdateInstaller
{
    public function __construct(
        private readonly GitHubReleaseService $github,
        private readonly UpdateEnvironment $environment,
        private readonly UpdateBackupService $backups,
        private readonly SafeReleaseArchive $archive,
    ) {}

    public function installPending(): ?SystemUpdate
    {
        $update = SystemUpdate::query()->where('status', 'pending')->oldest('approved_at')->first();

        return $update ? $this->install($update) : null;
    }

    public function install(SystemUpdate $update): SystemUpdate
    {
        return Cache::lock('system-update-installation', 3600)->block(1, function () use ($update): SystemUpdate {
            $diagnostics = $this->environment->diagnostics();
            if (! $diagnostics['ready']) {
                throw new RuntimeException('The managed update environment is not ready.');
            }

            if (! in_array($update->status, ['pending', 'failed'], true) || ! $update->isAvailable()) {
                throw new RuntimeException('This update is not approved for installation.');
            }

            return $this->performInstallation($update);
        });
    }

    public function rollback(SystemUpdate $update): SystemUpdate
    {
        return Cache::lock('system-update-installation', 3600)->block(1, function () use ($update): SystemUpdate {
            $diagnostics = $this->environment->diagnostics(requireInstallationEnabled: false);
            if (! $diagnostics['ready']) {
                throw new RuntimeException('The managed update environment is not ready for rollback.');
            }

            $previousRelease = realpath((string) data_get($update->metadata, 'previous_release'));
            $releasesPath = realpath((string) config('updater.releases_path'));
            if (
                $update->status !== 'completed'
                || $previousRelease === false
                || $releasesPath === false
                || ! $this->isWithin($previousRelease, $releasesPath)
            ) {
                throw new RuntimeException('No previous release is available for this update.');
            }

            $currentLink = (string) config('updater.current_link');
            $currentRelease = $this->currentReleaseTarget($currentLink);
            $expectedRelease = realpath((string) data_get($update->metadata, 'new_release'));
            if ($expectedRelease === false || $currentRelease !== $expectedRelease) {
                throw new RuntimeException('The installed release is no longer the active release.');
            }

            $this->runArtisan($currentRelease, ['down', '--render=errors::503', '--retry=60']);
            $switched = false;

            try {
                $this->runArtisan($previousRelease, ['optimize']);
                $this->switchCurrentLink($currentLink, $previousRelease);
                $switched = true;
                $this->reloadPhpRuntime();
                $this->runArtisan($previousRelease, ['queue:restart']);
                $this->runArtisan($previousRelease, ['up']);
                $this->assertHealth();
            } catch (Throwable $exception) {
                if ($switched) {
                    try {
                        $this->switchCurrentLink($currentLink, $currentRelease);
                        $this->reloadPhpRuntime();
                        $this->runArtisan($currentRelease, ['optimize'], false);
                        $this->runArtisan($currentRelease, ['queue:restart'], false);
                    } catch (Throwable) {
                        // Preserve the original rollback error below.
                    }
                }

                $this->runArtisan($currentRelease, ['up'], false);
                $update->forceFill(['error' => mb_substr($exception->getMessage(), 0, 4000)])->save();

                throw $exception;
            }

            $update->forceFill([
                'status' => 'rolled_back',
                'rolled_back_at' => now(),
                'error' => null,
            ])->save();
            Cache::forget('system-updates.available');

            return $update;
        });
    }

    private function performInstallation(SystemUpdate $update): SystemUpdate
    {
        $releasesPath = realpath((string) config('updater.releases_path'));
        $sharedPath = realpath((string) config('updater.shared_path'));
        $currentLink = (string) config('updater.current_link');
        if ($releasesPath === false || $sharedPath === false) {
            throw new RuntimeException('Managed update paths could not be resolved.');
        }

        $releaseName = 'v'.$update->version;
        if (preg_match('/^v\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $releaseName) !== 1) {
            throw new RuntimeException('The target release version is invalid.');
        }

        $releasePath = $releasesPath.DIRECTORY_SEPARATOR.$releaseName;
        $temporaryPath = $releasesPath.DIRECTORY_SEPARATOR.'.'.$releaseName.'-'.bin2hex(random_bytes(5));
        if (file_exists($releasePath) || file_exists($temporaryPath)) {
            throw new RuntimeException('The target release directory already exists.');
        }

        $updatesPath = $sharedPath.DIRECTORY_SEPARATOR.'updates';
        File::ensureDirectoryExists($updatesPath, 0700, true);
        $archivePath = $updatesPath.DIRECTORY_SEPARATOR.$update->asset_name;
        $oldRelease = $this->currentReleaseTarget($currentLink);
        $switched = false;
        $maintenance = false;
        $finalized = false;

        $update->forceFill([
            'status' => 'installing',
            'started_at' => now(),
            'error' => null,
        ])->save();
        Cache::put('system-updates.available', $update->fresh(), now()->addMinute());

        try {
            $this->github->download($update, $archivePath);
            $this->verifyDigest($archivePath, (string) $update->asset_digest);
            $this->archive->extract($archivePath, $temporaryPath);
            $this->validatePackage($temporaryPath, $update->version);
            $this->linkSharedData($temporaryPath, $sharedPath);

            $backup = $this->backups->create($update->version);
            $metadata = array_merge($update->metadata ?? [], [
                'backup' => $backup,
                'previous_release' => $oldRelease,
                'new_release' => $releasePath,
            ]);
            $update->forceFill(['metadata' => $metadata])->save();

            if (! rename($temporaryPath, $releasePath)) {
                throw new RuntimeException('The prepared release could not be finalized.');
            }
            $finalized = true;

            $this->runArtisan($oldRelease, ['down', '--render=errors::503', '--retry=60']);
            $maintenance = true;
            $this->runArtisan($releasePath, ['migrate', '--force', '--isolated']);
            $this->runArtisan($releasePath, ['optimize']);

            $this->switchCurrentLink($currentLink, $releasePath);
            $switched = true;
            $this->reloadPhpRuntime();
            $this->runArtisan($releasePath, ['queue:restart']);
            $this->runArtisan($releasePath, ['up']);
            $maintenance = false;
            $this->assertHealth();

            $update->forceFill([
                'status' => 'completed',
                'installed_at' => now(),
                'failed_at' => null,
                'error' => null,
            ])->save();
            Cache::forget('system-updates.available');
            File::delete($archivePath);

            return $update;
        } catch (Throwable $exception) {
            if ($switched) {
                try {
                    $this->switchCurrentLink($currentLink, $oldRelease);
                    $this->reloadPhpRuntime();
                    $this->runArtisan($oldRelease, ['optimize'], false);
                    $this->runArtisan($oldRelease, ['queue:restart'], false);
                } catch (Throwable) {
                    // Preserve the original installation error below.
                }
            }

            if ($maintenance) {
                $this->runArtisan($oldRelease, ['up'], false);
            }

            $update->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();
            Cache::forget('system-updates.available');

            if (is_dir($temporaryPath) && $this->isWithin($temporaryPath, $releasesPath)) {
                File::deleteDirectory($temporaryPath);
            }
            $activeRelease = is_link($currentLink) ? realpath($currentLink) : false;
            if (
                $finalized
                && is_dir($releasePath)
                && $this->isWithin($releasePath, $releasesPath)
                && $activeRelease !== realpath($releasePath)
            ) {
                File::deleteDirectory($releasePath);
            }
            File::delete($archivePath);

            throw $exception;
        }
    }

    private function verifyDigest(string $archivePath, string $expected): void
    {
        $expected = str_starts_with($expected, 'sha256:') ? substr($expected, 7) : $expected;
        $actual = hash_file('sha256', $archivePath);

        if ($actual === false || preg_match('/^[a-f0-9]{64}$/', $expected) !== 1 || ! hash_equals($expected, $actual)) {
            File::delete($archivePath);
            throw new RuntimeException('The update package failed SHA-256 verification.');
        }
    }

    private function validatePackage(string $path, string $version): void
    {
        foreach (['artisan', 'vendor/autoload.php', 'public/index.php', 'public/build/manifest.json', 'VERSION'] as $required) {
            if (! is_file($path.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $required))) {
                throw new RuntimeException("The update package is missing {$required}.");
            }
        }

        $packageVersion = trim((string) File::get($path.DIRECTORY_SEPARATOR.'VERSION'));
        if (! hash_equals($version, $packageVersion)) {
            throw new RuntimeException('The packaged application version does not match the approved release.');
        }
    }

    private function linkSharedData(string $releasePath, string $sharedPath): void
    {
        $environment = $sharedPath.DIRECTORY_SEPARATOR.'.env';
        $storage = $sharedPath.DIRECTORY_SEPARATOR.'storage';
        if (! symlink($environment, $releasePath.DIRECTORY_SEPARATOR.'.env')) {
            throw new RuntimeException('The shared environment link could not be created.');
        }

        if (! symlink($storage, $releasePath.DIRECTORY_SEPARATOR.'storage')) {
            throw new RuntimeException('The shared storage link could not be created.');
        }

        $publicStorage = $releasePath.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'storage';
        if (file_exists($publicStorage) || is_link($publicStorage)) {
            throw new RuntimeException('The update package unexpectedly contains public storage.');
        }

        if (! symlink($storage.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public', $publicStorage)) {
            throw new RuntimeException('The public storage link could not be created.');
        }

        File::ensureDirectoryExists($releasePath.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache', 0775, true);
    }

    private function currentReleaseTarget(string $currentLink): string
    {
        $target = readlink($currentLink);
        if ($target === false) {
            throw new RuntimeException('The active release link could not be read.');
        }

        if (! str_starts_with($target, DIRECTORY_SEPARATOR)) {
            $target = dirname($currentLink).DIRECTORY_SEPARATOR.$target;
        }

        $resolved = realpath($target);
        $releases = realpath((string) config('updater.releases_path'));
        if ($resolved === false || $releases === false || ! $this->isWithin($resolved, $releases)) {
            throw new RuntimeException('The active release is outside the managed releases directory.');
        }

        return $resolved;
    }

    private function switchCurrentLink(string $currentLink, string $releasePath): void
    {
        $temporaryLink = $currentLink.'.next-'.bin2hex(random_bytes(4));
        if (! symlink($releasePath, $temporaryLink)) {
            throw new RuntimeException('The next release link could not be created.');
        }

        if (! rename($temporaryLink, $currentLink)) {
            @unlink($temporaryLink);
            throw new RuntimeException('The active release link could not be switched.');
        }
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function runArtisan(string $releasePath, array $arguments, bool $throw = true): void
    {
        $command = [(string) config('updater.php_binary'), $releasePath.DIRECTORY_SEPARATOR.'artisan', ...$arguments, '--no-interaction'];
        $process = new Process($command, $releasePath);
        $process->setTimeout(900);
        $process->run();

        if ($throw && ! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'An Artisan update command failed.');
        }
    }

    private function assertHealth(): void
    {
        $url = (string) config('updater.health_url');
        if ($url === '') {
            throw new RuntimeException('The update health-check URL is not configured.');
        }

        $response = Http::connectTimeout(5)->timeout(15)->retry(3, 1000)->get($url);
        if (! $response->successful()) {
            throw new RuntimeException("The updated application health check returned HTTP {$response->status()}.");
        }
    }

    private function reloadPhpRuntime(): void
    {
        $command = config('updater.reload_command', []);
        if (! is_array($command) || $command === []) {
            throw new RuntimeException('The PHP runtime reload command is not configured.');
        }

        $process = new Process(array_values($command));
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'The PHP runtime could not be reloaded.');
        }
    }

    private function isWithin(string $path, string $parent): bool
    {
        $normalizedPath = rtrim(str_replace('\\', '/', $path), '/').'/';
        $normalizedParent = rtrim(str_replace('\\', '/', $parent), '/').'/';

        return str_starts_with($normalizedPath, $normalizedParent);
    }
}
