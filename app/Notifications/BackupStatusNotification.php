<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BackupRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class BackupStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly BackupRecord $backup) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $completed = $this->backup->status === 'completed';

        return [
            'type' => 'backup_status',
            'title' => $completed ? 'Backup completed successfully' : 'Backup failed',
            'message' => $completed
                ? Str::headline($this->backup->type).' backup is encrypted, stored, and ready for recovery.'
                : Str::headline($this->backup->type).' backup failed: '.($this->backup->error ?: 'Review the recovery center for details.'),
            'backup_uuid' => $this->backup->uuid,
            'status' => $this->backup->status,
            'url' => route('backups.index', ['tab' => 'backups']),
        ];
    }
}
