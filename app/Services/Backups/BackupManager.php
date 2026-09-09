<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Jobs\CreateChurchBackup;
use App\Models\BackupEvent;
use App\Models\BackupRecord;
use App\Models\Church;
use App\Models\User;
use App\Notifications\BackupStatusNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class BackupManager
{
    public function create(Church $church, string $type, ?User $creator = null, string $source = 'manual', array $destinationIds = []): BackupRecord
    {
        $backup = BackupRecord::query()->create([
            'uuid' => (string) Str::uuid(),
            'church_id' => $church->id,
            'created_by' => $creator?->id,
            'type' => $type,
            'source' => $source,
            'status' => 'queued',
            'stage' => 'queued',
            'progress' => 0,
            'disk' => (string) config('backups.disk', 'local'),
            'destination_ids' => $destinationIds,
        ]);
        $this->event($backup, 'queued', 'Backup queued for background processing.', $creator);
        CreateChurchBackup::dispatch($backup->id);

        return $backup;
    }

    public function process(BackupRecord $backup, BackupPackageBuilder $builder): void
    {
        $backup->forceFill(['status' => 'running', 'stage' => 'preparing', 'progress' => 5, 'started_at' => now(), 'error' => null])->save();
        $this->event($backup, 'preparing', 'Preparing the protected backup workspace.');

        try {
            $result = $builder->build($backup, function (string $stage, int $progress, string $message) use ($backup): void {
                $backup->forceFill(compact('stage', 'progress'))->save();
                $this->event($backup, $stage, $message);
            });
            $backup->forceFill($result + [
                'status' => 'completed',
                'stage' => 'completed',
                'progress' => 100,
                'completed_at' => now(),
                'verified_at' => now(),
            ])->save();
            $this->event($backup, 'completed', 'Backup completed and its encrypted package checksum was recorded.');
            $this->notifyAdministrators($backup);
        } catch (Throwable $exception) {
            report($exception);
            $backup->forceFill([
                'status' => 'failed', 'stage' => 'failed', 'failed_at' => now(), 'error' => $exception->getMessage(),
            ])->save();
            $this->event($backup, 'failed', 'Backup failed: '.$exception->getMessage(), null, 'error');
            $this->notifyAdministrators($backup);
        }
    }

    public function verify(BackupRecord $backup, BackupPackageValidator $validator): array
    {
        if ($backup->status !== 'completed' || ! $backup->path) {
            throw new RuntimeException('Only completed backup packages can be verified.');
        }
        $temporary = storage_path('app/backup-work/verify-'.$backup->uuid.'.ecbak');
        if (! is_dir(dirname($temporary))) {
            mkdir(dirname($temporary), 0750, true);
        }
        $stream = Storage::disk($backup->disk)->readStream($backup->path);
        $output = fopen($temporary, 'wb');
        if ($stream === false || $output === false) {
            throw new RuntimeException('The backup package could not be opened for verification.');
        }
        stream_copy_to_stream($stream, $output);
        fclose($stream);
        fclose($output);

        try {
            $actualChecksum = hash_file('sha256', $temporary) ?: '';
            if (! hash_equals((string) $backup->checksum, $actualChecksum)) {
                throw new RuntimeException('Stored package checksum mismatch.');
            }
            $validation = $validator->validate($temporary, $backup->church()->firstOrFail());
            $backup->forceFill(['verified_at' => now()])->save();
            $this->event($backup, 'verified', 'Backup health and restore compatibility verification passed.');

            return $validation;
        } finally {
            @unlink($temporary);
        }
    }

    public function delete(BackupRecord $backup, ?User $user = null): void
    {
        if ($backup->path) {
            Storage::disk($backup->disk)->delete($backup->path);
        }
        $this->event($backup, 'deleted', 'Backup package deleted by an authorized administrator.', $user, 'warning');
        $backup->delete();
    }

    private function event(BackupRecord $backup, string $stage, string $message, ?User $user = null, string $level = 'info'): void
    {
        BackupEvent::query()->create([
            'church_id' => $backup->church_id,
            'backup_record_id' => $backup->id,
            'user_id' => $user?->id,
            'level' => $level,
            'stage' => $stage,
            'message' => $message,
        ]);
    }

    private function notifyAdministrators(BackupRecord $backup): void
    {
        User::query()
            ->where('church_id', $backup->church_id)
            ->whereHas('roles', fn ($role) => $role
                ->where('name', 'Super Administrator')
                ->orWhereHas('permissions', fn ($permission) => $permission->where('name', 'backup.configure')))
            ->each(fn (User $user) => $user->notify(new BackupStatusNotification($backup)));
    }
}
