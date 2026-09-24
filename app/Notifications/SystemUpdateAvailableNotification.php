<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SystemUpdate;
use App\Models\User;
use App\Services\Communications\NotificationPreferenceResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
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
        $channels = ['database'];
        if ($notifiable instanceof User && app(NotificationPreferenceResolver::class)->emailEnabled($notifiable, 'system')) {
            $channels[] = 'mail';
        }

        return $channels;
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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->from((string) config('mail.from.address'), 'EcclesiaOS')
            ->subject('EcclesiaOS update available')
            ->greeting('Hello '.$notifiable->name.',')
            ->line("Version {$this->update->version} is ready for review.")
            ->action('Review System Update', route('system-updates.index'))
            ->markdown('notifications::email', ['brandName' => 'EcclesiaOS']);
    }
}
