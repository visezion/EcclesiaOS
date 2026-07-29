<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SystemUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class SystemUpdateAvailableNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly SystemUpdate $update) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'system_update',
            'title' => 'System update available',
            'message' => "Version {$this->update->version} is ready for review.",
            'version' => $this->update->version,
            'url' => route('system-updates.index'),
        ];
    }
}
