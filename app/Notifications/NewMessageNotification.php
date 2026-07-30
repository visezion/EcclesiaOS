<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MessageThread;
use App\Models\UserNotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly MessageThread $thread,
        private readonly ?string $senderName = null,
        private readonly ?string $customMessage = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $preference = UserNotificationPreference::query()->where('user_id', $notifiable->id)->first();
        $messageChannels = data_get($preference?->category_channels, 'messages', []);
        if (in_array('email', $messageChannels, true) && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'message',
            'title' => $this->customMessage ? 'Conversation update' : 'New internal message',
            'message' => $this->customMessage ?: (($this->senderName ? $this->senderName.': ' : '').($this->thread->subject ?: 'You received a new message.')),
            'url' => route('messages.show', $this->thread),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->customMessage ? 'Conversation update' : 'New internal message')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->customMessage ?: (($this->senderName ? $this->senderName.' sent a message: ' : '').($this->thread->subject ?: 'New conversation')))
            ->action('Open Message Center', route('messages.show', $this->thread))
            ->line('This notification was sent according to your communication preferences.');
    }
}
