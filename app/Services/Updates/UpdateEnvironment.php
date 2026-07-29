<?php

declare(strict_types=1);

namespace App\Services\Updates;

use ZipArchive;

final class UpdateEnvironment
{
    /**
     * @return array{ready: bool, checks: array<int, array{label: string, ready: bool, message: string}>}
     */
    public function diagnostics(bool $requireInstallationEnabled = true): array
    {
        $releasesPath = trim((string) config('updater.releases_path'));
        $sharedPath = trim((string) config('updater.shared_path'));
        $currentLink = trim((string) config('updater.current_link'));
        $resolvedReleases = $releasesPath !== '' ? realpath($releasesPath) : false;
        $resolvedShared = $sharedPath !== '' ? realpath($sharedPath) : false;
        $currentTarget = $currentLink !== '' && is_link($currentLink) ? realpath($currentLink) : false;
        $databaseConnection = (string) config('database.default');
        $databaseConfig = config("database.connections.{$databaseConnection}", []);
        $sqlitePath = ($databaseConfig['driver'] ?? null) === 'sqlite'
            ? realpath((string) ($databaseConfig['database'] ?? ''))
            : null;
        $databaseIsShared = $sqlitePath === null
            || ($sqlitePath !== false && $resolvedShared !== false && $this->isWithin($sqlitePath, $resolvedShared));
        $reloadCommand = config('updater.reload_command', []);
        $reloadExecutable = is_array($reloadCommand) ? ($reloadCommand[0] ?? null) : null;
        $reloadIsSafe = is_string($reloadExecutable)
            && str_starts_with($reloadExecutable, DIRECTORY_SEPARATOR)
            && is_executable($reloadExecutable);
        $pathsAreDistinct = $resolvedReleases !== false
            && $resolvedShared !== false
            && $resolvedReleases !== $resolvedShared
            && ! $this->isWithin($resolvedReleases, $resolvedShared)
            && ! $this->isWithin($resolvedShared, $resolvedReleases);

        $checks = [
            $this->check('Installation enabled', ! $requireInstallationEnabled || (bool) config('updater.install_enabled'), 'Set UPDATER_INSTALL_ENABLED=true after configuring the managed layout.'),
            $this->check('Production operating system', PHP_OS_FAMILY !== 'Windows', 'Atomic release switching requires a Linux or Unix production host.'),
            $this->check('ZIP support', class_exists(ZipArchive::class), 'Install and enable the PHP ZIP extension.'),
            $this->check('Process support', function_exists('proc_open'), 'Enable proc_open for database backup commands and Artisan operations.'),
            $this->check('Releases directory', $releasesPath !== '' && is_dir($releasesPath) && is_writable($releasesPath), 'Configure a writable UPDATER_RELEASES_PATH.'),
            $this->check('Shared directory', $sharedPath !== '' && is_dir($sharedPath) && is_writable($sharedPath), 'Configure a writable UPDATER_SHARED_PATH.'),
            $this->check('Isolated managed paths', $pathsAreDistinct, 'The releases and shared directories must be separate and must not contain one another.'),
            $this->check('Shared environment', $sharedPath !== '' && is_file($sharedPath.DIRECTORY_SEPARATOR.'.env'), 'Move the production .env file into the shared directory.'),
            $this->check('Shared storage', $sharedPath !== '' && is_dir($sharedPath.DIRECTORY_SEPARATOR.'storage'), 'Move Laravel storage into the shared directory.'),
            $this->check('Public upload storage', $sharedPath !== '' && is_dir($sharedPath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'), 'Create shared/storage/app/public for church uploads.'),
            $this->check('Persistent database', $databaseIsShared, 'A production SQLite database must be stored inside the shared directory.'),
            $this->check('PHP runtime reload', $reloadIsSafe, 'Configure UPDATER_RELOAD_COMMAND_JSON with an absolute executable path and shell-free arguments.'),
            $this->check('Current release link', $currentLink !== '' && is_link($currentLink), 'Configure UPDATER_CURRENT_LINK as a symbolic link to the active release.'),
            $this->check('Current link parent', $currentLink !== '' && is_dir(dirname($currentLink)) && is_writable(dirname($currentLink)), 'The directory containing UPDATER_CURRENT_LINK must be writable.'),
            $this->check('Managed active release', $currentTarget !== false && $resolvedReleases !== false && $this->isWithin($currentTarget, $resolvedReleases), 'The current link must target a directory inside the releases directory.'),
        ];

        return [
            'ready' => collect($checks)->every(fn (array $check): bool => $check['ready']),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{label: string, ready: bool, message: string}
     */
    private function check(string $label, bool $ready, string $message): array
    {
        return compact('label', 'ready', 'message');
    }

    private function isWithin(string $path, string $parent): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/').'/';
        $parent = rtrim(str_replace('\\', '/', $parent), '/').'/';

        return str_starts_with($path, $parent);
    }
}
