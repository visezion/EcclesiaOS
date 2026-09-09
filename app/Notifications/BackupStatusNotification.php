<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BackupRecord;
use App\Models\User;
use App\Services\Communications\NotificationPreferenceResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class BackupStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly BackupRecord $backup) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($notifiable instanceof User && app(NotificationPreferenceResolver::class)->emailEnabled($notifiable, 'system')) {
            $channels[] = 'mail';
        }

        return $channels;
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

    public function toMail(object $notifiable): MailMessage
    {
        $completed = $this->backup->status === 'completed';

        return (new MailMessage)
            ->subject($completed ? 'Backup completed successfully' : 'Backup failed')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($completed
                ? 'Your '.Str::headline($this->backup->type).' backup is encrypted, stored, and ready for recovery.'
                : 'Your '.Str::headline($this->backup->type).' backup failed. Review the recovery center for details.')
            ->action('Open Backup Center', route('backups.index', ['tab' => 'backups']));
    }
}
