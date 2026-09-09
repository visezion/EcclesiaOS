<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Models\BackupDestination;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class BackupDestinationManager
{
    public function disk(BackupDestination $destination): FilesystemAdapter
    {
        $configuration = $destination->configuration ?? [];
        $disk = match ($destination->driver) {
            'local' => [
                'driver' => 'local',
                'root' => storage_path('app/backup-destinations/'.$destination->church_id.'/'.$destination->id),
                'throw' => true,
            ],
            's3' => [
                'driver' => 's3',
                'key' => $configuration['key'] ?? null,
                'secret' => $configuration['secret'] ?? null,
                'region' => $configuration['region'] ?? 'auto',
                'bucket' => $configuration['bucket'] ?? null,
                'endpoint' => $configuration['endpoint'] ?? null,
                'use_path_style_endpoint' => (bool) ($configuration['use_path_style_endpoint'] ?? false),
                'throw' => true,
            ],
            'sftp' => [
                'driver' => 'sftp',
                'host' => $configuration['host'] ?? null,
                'username' => $configuration['username'] ?? null,
                'password' => $configuration['password'] ?? null,
                'port' => (int) ($configuration['port'] ?? 22),
                'root' => $configuration['root'] ?? '/ecclesiaos-backups',
                'timeout' => 20,
                'throw' => true,
            ],
            default => throw new RuntimeException('Unsupported backup destination driver.'),
        };

        return Storage::build($disk);
    }

    public function test(BackupDestination $destination): void
    {
        $disk = $this->disk($destination);
        $path = '.ecclesiaos-health-check-'.uniqid().'.txt';
        $contents = 'EcclesiaOS backup destination check '.now()->toIso8601String();
        if (! $disk->put($path, $contents) || $disk->get($path) !== $contents) {
            throw new RuntimeException('The destination write/read verification failed.');
        }
        $disk->delete($path);
    }

    /** @return array<int, array<string, mixed>> */
    public function copy(string $sourcePath, string $filename, iterable $destinations): array
    {
        $copies = [];
        foreach ($destinations as $destination) {
            try {
                $stream = fopen($sourcePath, 'rb');
                if ($stream === false || ! $this->disk($destination)->writeStream($filename, $stream)) {
                    throw new RuntimeException('The destination rejected the backup stream.');
                }
                fclose($stream);
                $copies[] = ['destination_id' => $destination->id, 'name' => $destination->name, 'status' => 'completed', 'path' => $filename];
            } catch (\Throwable $exception) {
                isset($stream) && is_resource($stream) && fclose($stream);
                report($exception);
                $copies[] = ['destination_id' => $destination->id, 'name' => $destination->name, 'status' => 'failed', 'error' => $exception->getMessage()];
            }
        }

        return $copies;
    }
}
