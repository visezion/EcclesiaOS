<?php

namespace App\Support;

use Illuminate\Notifications\Messages\MailMessage;

final class ChurchMailBranding
{
    public function apply(MailMessage $message, object $notifiable): MailMessage
    {
        $churchName = trim((string) $notifiable->church?->name);

        return $message->markdown('notifications::email', [
            'brandName' => $churchName !== '' ? $churchName : config('app.name', 'EcclesiaOS'),
        ]);
    }
}
