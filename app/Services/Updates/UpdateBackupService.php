<?php

declare(strict_types=1);

namespace App\Services\Updates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

final class UpdateBackupService
{
    /**
     * @return array<string, string|null>
     */
    public function create(string $version): array
    {
        $sharedPath = rtrim((string) config('updater.shared_path'), DIRECTORY_SEPARATOR);
        $backupPath = $sharedPath.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.'updates'
            .DIRECTORY_SEPARATOR.now()->format('Ymd-His').'-v'.$version;
        File::ensureDirectoryExists($backupPath, 0700, true);

        $databasePath = $this->backupDatabase($backupPath);
        $environmentPath = $this->backupEnvironment($sharedPath, $backupPath);
        $storagePath = config('updater.backup_storage')
            ? $this->backupStorage($sharedPath, $backupPath)
            : null;

        $this->prune(dirname($backupPath));

        return [
            'directory' => $backupPath,
            'database' => $databasePath,
            'environment' => $environmentPath,
            'storage' => $storagePath,
        ];
    }

    private function backupDatabase(string $backupPath): string
    {
        $connection = (string) config('database.default');
        $configuration = config("database.connections.{$connection}", []);
        $driver = (string) ($configuration['driver'] ?? '');

        return match ($driver) {
            'sqlite' => $this->backupSqlite($configuration, $backupPath),
            'mysql', 'mariadb' => $this->backupMysql($configuration, $backupPath),
            'pgsql' => $this->backupPostgres($configuration, $backupPath),
            default => throw new RuntimeException("Automatic database backup is not supported for {$driver}."),
        };
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function backupSqlite(array $configuration, string $backupPath): string
    {
        $source = (string) ($configuration['database'] ?? '');
        if ($source === '' || ! is_file($source)) {
            throw new RuntimeException('The SQLite database file could not be found.');
        }

        DB::statement('PRAGMA wal_checkpoint(FULL)');
        $destination = $backupPath.DIRECTORY_SEPARATOR.'database.sqlite';
        if (! copy($source, $destination)) {
            throw new RuntimeException('The SQLite database backup failed.');
        }

        return $destination;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function backupMysql(array $configuration, string $backupPath): string
    {
        $defaultsFile = $backupPath.DIRECTORY_SEPARATOR.'.mysql-client.cnf';
        $values = [
            'host' => $configuration['host'] ?? '127.0.0.1',
            'port' => $configuration['port'] ?? 3306,
            'user' => $configuration['username'] ?? '',
            'password' => $configuration['password'] ?? '',
        ];

        foreach ($values as $value) {
            if (str_contains((string) $value, "\n") || str_contains((string) $value, "\r")) {
                throw new RuntimeException('The database backup configuration is invalid.');
            }
        }

        $quote = fn (mixed $value): string => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value).'"';
        $contents = "[client]\n";
        foreach ($values as $key => $value) {
            $contents .= $key.'='.$quote($value)."\n";
        }

        File::put($defaultsFile, $contents);
        @chmod($defaultsFile, 0600);

        $destination = $backupPath.DIRECTORY_SEPARATOR.'database.sql';
        try {
            $this->runToFile([
                (string) config('updater.mysqldump_path'),
                '--defaults-extra-file='.$defaultsFile,
                '--single-transaction',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                '--no-tablespaces',
                (string) ($configuration['database'] ?? ''),
            ], $destination);
        } finally {
            File::delete($defaultsFile);
        }

        return $destination;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function backupPostgres(array $configuration, string $backupPath): string
    {
        $destination = $backupPath.DIRECTORY_SEPARATOR.'database.sql';
        $this->runToFile([
            (string) config('updater.pg_dump_path'),
            '--host='.((string) ($configuration['host'] ?? '127.0.0.1')),
            '--port='.((string) ($configuration['port'] ?? 5432)),
            '--username='.((string) ($configuration['username'] ?? '')),
            '--no-owner',
            '--no-privileges',
            (string) ($configuration['database'] ?? ''),
        ], $destination, ['PGPASSWORD' => (string) ($configuration['password'] ?? '')]);

        return $destination;
    }

    private function backupEnvironment(string $sharedPath, string $backupPath): ?string
    {
        $source = $sharedPath.DIRECTORY_SEPARATOR.'.env';
        $destination = $backupPath.DIRECTORY_SEPARATOR.'environment.env.backup';
        if (! is_file($source) && (bool) env('APP_CONTAINERIZED', false)) {
            return null;
        }

        if (! is_file($source) || ! copy($source, $destination)) {
            throw new RuntimeException('The shared environment file could not be backed up.');
        }

        @chmod($destination, 0600);

        return $destination;
    }

    private function backupStorage(string $sharedPath, string $backupPath): ?string
    {
        $source = $sharedPath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app';
        if (! is_dir($source)) {
            return null;
        }

        $destination = $backupPath.DIRECTORY_SEPARATOR.'storage-app.zip';
        $zip = new ZipArchive;
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The uploaded-file backup archive could not be created.');
        }

        $rootLength = strlen(rtrim($source, DIRECTORY_SEPARATOR)) + 1;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if ($file->isLink() || ! $file->isFile()) {
                continue;
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), $rootLength));
            if (! $zip->addFile($file->getPathname(), $relative)) {
                $zip->close();
                throw new RuntimeException("The uploaded file {$relative} could not be backed up.");
            }
        }

        if (! $zip->close()) {
            throw new RuntimeException('The uploaded-file backup archive could not be finalized.');
        }

        return $destination;
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>  $environment
     */
    private function runToFile(array $command, string $destination, array $environment = []): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $destination, 'wb'],
            2 => ['pipe', 'w'],
        ];
        $inheritedEnvironment = getenv();
        $processEnvironment = $environment === []
            ? null
            : array_merge(is_array($inheritedEnvironment) ? $inheritedEnvironment : [], $environment);
        $process = proc_open($command, $descriptors, $pipes, null, $processEnvironment);
        if (! is_resource($process)) {
            throw new RuntimeException('The database backup process could not be started.');
        }

        fclose($pipes[0]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || ! is_file($destination) || filesize($destination) === 0) {
            File::delete($destination);
            throw new RuntimeException('The database backup failed: '.trim((string) $error));
        }
    }

    private function prune(string $updatesBackupPath): void
    {
        $retention = max(1, (int) config('updater.backup_retention'));
        collect(File::directories($updatesBackupPath))
            ->sortDesc()
            ->slice($retention)
            ->each(function (string $path) use ($updatesBackupPath): void {
                if (str_starts_with(realpath($path) ?: '', realpath($updatesBackupPath).DIRECTORY_SEPARATOR)) {
                    File::deleteDirectory($path);
                }
            });
    }
}
