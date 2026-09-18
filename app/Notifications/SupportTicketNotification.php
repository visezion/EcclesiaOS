<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Communications\NotificationPreferenceResolver;
use App\Support\ChurchMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SupportTicketNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SupportTicket $ticket,
        private readonly string $title,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($notifiable instanceof User && app(NotificationPreferenceResolver::class)->emailEnabled($notifiable, 'system')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'support_ticket',
            'title' => $this->title,
            'message' => $this->message,
            'url' => route('support.tickets.show', $this->ticket),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return app(ChurchMailBranding::class)->apply((new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->message)
            ->action('Open Support Ticket', route('support.tickets.show', $this->ticket)), $notifiable);
    }
}
