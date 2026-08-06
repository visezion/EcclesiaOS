<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CommunicationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class CommunicationDeliveryNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly CommunicationDelivery $delivery) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'communication',
            'title' => $this->delivery->subject ?: 'New notification',
            'message' => $this->delivery->body_excerpt ?: 'You have a new notification.',
            'url' => data_get($this->delivery->metadata, 'url', route('account.settings').'#notifications'),
            'delivery_id' => $this->delivery->opaqueId(),
            'event_type' => $this->delivery->event_type,
            'category' => $this->delivery->category,
        ];
    }
}
