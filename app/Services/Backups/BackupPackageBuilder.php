<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Models\BackupDestination;
use App\Models\BackupRecord;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

final class BackupPackageBuilder
{
    public function __construct(
        private readonly PortableSnapshotExporter $exporter,
        private readonly BackupArchiveCryptor $cryptor,
        private readonly BackupDestinationManager $destinations,
    ) {}

    /** @param callable(string, int, string): void $progress */
    public function build(BackupRecord $backup, callable $progress): array
    {
        $church = $backup->church()->firstOrFail();
        $work = storage_path('app/backup-work/'.$backup->uuid);
        File::deleteDirectory($work);
        File::ensureDirectoryExists($work.'/database');
        File::ensureDirectoryExists($work.'/storage');
        $tables = [];
        $recordCount = 0;
        $fileCount = 0;

        try {
            if (in_array($backup->type, ['database', 'full', 'files'], true)) {
                $progress('exporting_database', 18, 'Exporting a consistent tenant database snapshot.');
                $snapshot = $this->exporter->export($church);
                $tables = $snapshot['tables'];
                if (in_array($backup->type, ['database', 'full'], true)) {
                    $recordCount = $snapshot['record_count'];
                    foreach ($tables as $table => $rows) {
                        File::put($work.'/database/'.$table.'.json', json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                    }
                }
            }

            $progress('archiving_files', 42, 'Collecting portable church files and media.');
            if (in_array($backup->type, ['files', 'full'], true)) {
                $fileCount = $this->copyReferencedFiles($work.'/storage', $tables, (int) $church->id);
            }

            File::put($work.'/tenant.json', json_encode([
                'id' => $church->id,
                'name' => $church->name,
                'slug' => $church->slug,
                'timezone' => $church->timezone,
                'currency' => $church->currency,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            File::put($work.'/permissions.json', json_encode([
                'roles' => $tables['roles'] ?? [],
                'permissions' => $tables['permissions'] ?? [],
                'assignments' => $tables['permission_role'] ?? [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $checksums = $this->checksums($work);
            File::put($work.'/checksums.json', json_encode($checksums, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $manifest = [
                'backup_format' => (int) config('backups.format_version', 1),
                'application_version' => trim((string) File::get(base_path('VERSION'))),
                'database_engine' => config('database.default'),
                'schema_version' => (string) (\DB::table('migrations')->max('migration') ?? 'unknown'),
                'tenant_id' => $church->id,
                'tenant_name' => $church->name,
                'tenant_slug' => $church->slug,
                'created_at' => now()->utc()->toIso8601String(),
                'backup_type' => $backup->type,
                'database_included' => in_array($backup->type, ['database', 'full'], true),
                'storage_included' => in_array($backup->type, ['files', 'full'], true),
                'record_count' => $recordCount,
                'file_count' => $fileCount,
                'encryption' => 'AES-256-CTR-HMAC-SHA256',
            ];
            File::put($work.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $progress('compressing', 61, 'Compressing the portable backup package.');
            $zipPath = storage_path('app/backup-work/'.$backup->uuid.'.zip');
            $this->zip($work, $zipPath);
            $progress('encrypting', 74, 'Encrypting the package with authenticated encryption.');
            $encryptedPath = storage_path('app/backup-work/'.$backup->uuid.'.ecbak');
            $this->cryptor->encrypt($zipPath, $encryptedPath);
            $checksum = hash_file('sha256', $encryptedPath);
            if ($checksum === false) {
                throw new RuntimeException('The encrypted backup checksum could not be generated.');
            }

            $progress('uploading', 86, 'Writing the encrypted package to protected storage.');
            $filename = 'ecclesiaos-'.$church->slug.'-'.$backup->type.'-'.now()->format('Ymd-His').'.ecbak';
            $path = trim((string) config('backups.root'), '/').'/'.$church->id.'/'.$filename;
            $stream = fopen($encryptedPath, 'rb');
            if ($stream === false || ! Storage::disk($backup->disk)->writeStream($path, $stream)) {
                is_resource($stream) && fclose($stream);
                throw new RuntimeException('The backup package could not be written to protected storage.');
            }
            fclose($stream);
            $destinationRecords = BackupDestination::query()
                ->where('church_id', $church->id)
                ->where('is_active', true)
                ->whereIn('id', $backup->destination_ids ?? [])
                ->get();
            $copies = $this->destinations->copy($encryptedPath, $filename, $destinationRecords);

            return [
                'manifest' => $manifest,
                'path' => $path,
                'filename' => $filename,
                'size_bytes' => filesize($encryptedPath) ?: 0,
                'checksum' => $checksum,
                'record_count' => $recordCount,
                'file_count' => $fileCount,
                'copies' => $copies,
            ];
        } finally {
            File::deleteDirectory($work);
            File::delete(storage_path('app/backup-work/'.$backup->uuid.'.zip'));
            File::delete(storage_path('app/backup-work/'.$backup->uuid.'.ecbak'));
        }
    }

    /** @param array<string, array<int, array<string, mixed>>> $tables */
    private function copyReferencedFiles(string $destination, array $tables, int $churchId): int
    {
        $candidates = collect($tables)->flatten()->filter(fn ($value): bool => is_string($value) && strlen($value) < 1000)
            ->map(fn (string $value): string => ltrim(str_replace('\\', '/', $value), '/'))
            ->filter(fn (string $value): bool => ! str_contains($value, '://'))
            ->unique();
        $count = 0;
        foreach (['public', 'local'] as $disk) {
            foreach ($candidates as $candidate) {
                if (! Storage::disk($disk)->exists($candidate) || Storage::disk($disk)->directoryExists($candidate)) {
                    continue;
                }
                $target = $destination.'/'.$disk.'/'.$candidate;
                File::ensureDirectoryExists(dirname($target));
                $source = Storage::disk($disk)->readStream($candidate);
                $output = fopen($target, 'wb');
                if ($source !== false && $output !== false) {
                    stream_copy_to_stream($source, $output);
                    $count++;
                }
                is_resource($source) && fclose($source);
                is_resource($output) && fclose($output);
            }

            foreach (Storage::disk($disk)->allFiles() as $path) {
                if (! preg_match('~(^|/)'.preg_quote((string) $churchId, '~').'(/|$)~', $path)) {
                    continue;
                }
                if (collect((array) config('backups.excluded_storage_prefixes'))->contains(fn (string $prefix): bool => str_starts_with($path, $prefix))) {
                    continue;
                }
                $target = $destination.'/'.$disk.'/'.$path;
                if (File::exists($target)) {
                    continue;
                }
                File::ensureDirectoryExists(dirname($target));
                $source = Storage::disk($disk)->readStream($path);
                $output = fopen($target, 'wb');
                if ($source !== false && $output !== false) {
                    stream_copy_to_stream($source, $output);
                    $count++;
                }
                is_resource($source) && fclose($source);
                is_resource($output) && fclose($output);
            }
        }

        return $count;
    }

    /** @return array<string, string> */
    private function checksums(string $root): array
    {
        $checksums = [];
        foreach (File::allFiles($root) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $checksums[$relative] = hash_file('sha256', $file->getPathname()) ?: '';
        }
        ksort($checksums);

        return $checksums;
    }

    private function zip(string $source, string $destination): void
    {
        $zip = new ZipArchive;
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The backup archive could not be created.');
        }
        foreach (File::allFiles($source) as $file) {
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $file->getRelativePathname()));
        }
        $zip->close();
    }
}
