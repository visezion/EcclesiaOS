<?php

namespace App\Notifications;

use App\Support\ChurchMailBranding;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

final class PasswordResetNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return app(ChurchMailBranding::class)->apply(parent::toMail($notifiable), $notifiable);
    }
}
