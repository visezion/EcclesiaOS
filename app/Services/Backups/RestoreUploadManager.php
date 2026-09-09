<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Models\BackupRestoreUpload;
use App\Models\Church;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

final class RestoreUploadManager
{
    public function start(Church $church, User $user, string $filename, int $totalSize, int $chunkSize, ?string $checksum): BackupRestoreUpload
    {
        $chunkSize = min(max(1024 * 1024, $chunkSize), (int) config('backups.max_chunk_size'));
        $totalChunks = (int) ceil($totalSize / $chunkSize);
        if ($totalSize < 1 || $totalChunks < 1) {
            throw new RuntimeException('The backup package size is invalid.');
        }
        $uuid = (string) Str::uuid();
        $path = 'restore-uploads/'.$church->id.'/'.$uuid;
        File::ensureDirectoryExists(storage_path('app/'.$path.'/chunks'));

        return BackupRestoreUpload::query()->create([
            'uuid' => $uuid,
            'church_id' => $church->id,
            'user_id' => $user->id,
            'filename' => basename($filename),
            'total_size' => $totalSize,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
            'received_chunks' => [],
            'expected_checksum' => $checksum,
            'path' => $path,
            'status' => 'uploading',
            'expires_at' => now()->addHours((int) config('backups.upload_expiry_hours')),
        ]);
    }

    public function chunk(BackupRestoreUpload $upload, int $index, UploadedFile $chunk): BackupRestoreUpload
    {
        if ($upload->status !== 'uploading' || $index < 0 || $index >= $upload->total_chunks) {
            throw new RuntimeException('This upload cannot accept that chunk.');
        }
        $received = collect($upload->received_chunks ?? [])->map(fn ($value): int => (int) $value);
        if (! $received->contains($index)) {
            $chunk->move(storage_path('app/'.$upload->path.'/chunks'), sprintf('%08d.part', $index));
            $received->push($index);
        }
        $received = $received->unique()->sort()->values();
        $upload->forceFill([
            'received_chunks' => $received->all(),
            'progress' => min(99, (int) floor(($received->count() / $upload->total_chunks) * 100)),
        ])->save();

        return $upload;
    }

    public function complete(BackupRestoreUpload $upload): BackupRestoreUpload
    {
        $received = collect($upload->received_chunks ?? [])->unique();
        if ($received->count() !== $upload->total_chunks) {
            throw new RuntimeException('Upload is incomplete. Missing chunks must be resumed first.');
        }
        $package = storage_path('app/'.$upload->path.'/package.ecbak');
        $output = fopen($package, 'wb');
        if ($output === false) {
            throw new RuntimeException('The uploaded package could not be assembled.');
        }
        try {
            for ($index = 0; $index < $upload->total_chunks; $index++) {
                $part = fopen(storage_path('app/'.$upload->path.'/chunks/'.sprintf('%08d.part', $index)), 'rb');
                if ($part === false) {
                    throw new RuntimeException('A required upload chunk is missing.');
                }
                stream_copy_to_stream($part, $output);
                fclose($part);
            }
        } finally {
            fclose($output);
        }
        if ((filesize($package) ?: 0) !== $upload->total_size) {
            throw new RuntimeException('The assembled backup size does not match the declared upload size.');
        }
        $checksum = hash_file('sha256', $package) ?: '';
        if ($upload->expected_checksum && ! hash_equals($upload->expected_checksum, $checksum)) {
            throw new RuntimeException('The uploaded backup checksum does not match.');
        }
        File::deleteDirectory(storage_path('app/'.$upload->path.'/chunks'));
        $upload->forceFill(['status' => 'uploaded', 'progress' => 100])->save();

        return $upload;
    }
}
