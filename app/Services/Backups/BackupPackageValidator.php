<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Models\Church;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

final class BackupPackageValidator
{
    public function __construct(private readonly BackupArchiveCryptor $cryptor) {}

    /** @return array<string, mixed> */
    public function validate(string $packagePath, Church $church): array
    {
        $work = storage_path('app/backup-work/validate-'.uniqid());
        File::ensureDirectoryExists($work);
        $zipPath = $work.'/package.zip';
        $extractPath = $work.'/package';
        File::ensureDirectoryExists($extractPath);

        try {
            if (! $this->cryptor->isEncryptedPackage($packagePath)) {
                throw new RuntimeException('Only encrypted EcclesiaOS .ecbak packages are accepted.');
            }
            $this->cryptor->decrypt($packagePath, $zipPath);
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('The decrypted backup archive is invalid.');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                if ($name === '' || str_starts_with($name, '/') || str_contains($name, '../') || preg_match('/^[A-Za-z]:/', $name)) {
                    $zip->close();
                    throw new RuntimeException('The backup contains an unsafe archive path.');
                }
            }
            if (! $zip->extractTo($extractPath)) {
                $zip->close();
                throw new RuntimeException('The backup archive could not be inspected.');
            }
            $zip->close();

            $manifest = $this->json($extractPath.'/manifest.json');
            $checksums = $this->json($extractPath.'/checksums.json');
            foreach ($checksums as $relative => $expected) {
                $path = $extractPath.'/'.str_replace('/', DIRECTORY_SEPARATOR, (string) $relative);
                if (! File::isFile($path) || ! hash_equals((string) $expected, hash_file('sha256', $path) ?: '')) {
                    throw new RuntimeException('Integrity verification failed for '.$relative.'.');
                }
            }

            $currentVersion = trim((string) File::get(base_path('VERSION')));
            $formatCompatible = (int) ($manifest['backup_format'] ?? 0) === (int) config('backups.format_version');
            $applicationCompatible = version_compare((string) ($manifest['application_version'] ?? '0.0.0'), $currentVersion, '<=');
            $databaseCompatible = (string) ($manifest['database_engine'] ?? '') === (string) config('database.default');
            $churchMatches = (int) ($manifest['tenant_id'] ?? 0) === (int) $church->id
                || (string) ($manifest['tenant_slug'] ?? '') === (string) $church->slug;
            $packageSize = filesize($packagePath) ?: 0;
            $availableStorage = disk_free_space(storage_path('app')) ?: null;
            $storageAvailable = $availableStorage === null || $availableStorage > ($packageSize * 3);

            return [
                'safe_to_restore' => $formatCompatible && $applicationCompatible && $databaseCompatible && $churchMatches && $storageAvailable,
                'checksum_valid' => true,
                'encryption_valid' => true,
                'format_compatible' => $formatCompatible,
                'application_compatible' => $applicationCompatible,
                'database_compatible' => $databaseCompatible,
                'church_matches' => $churchMatches,
                'storage_available' => $storageAvailable,
                'current_application_version' => $currentVersion,
                'manifest' => $manifest,
                'package_size' => $packageSize,
                'available_storage' => $availableStorage,
                'validated_at' => now()->toIso8601String(),
            ];
        } finally {
            File::deleteDirectory($work);
        }
    }

    /** @return array<string, mixed> */
    private function json(string $path): array
    {
        if (! File::isFile($path)) {
            throw new RuntimeException(basename($path).' is missing from the backup package.');
        }
        $value = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($value)) {
            throw new RuntimeException(basename($path).' is invalid.');
        }

        return $value;
    }
}
