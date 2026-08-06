<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class CommunicationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $notificationSubject,
        public readonly string $notificationBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notificationSubject);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.communication');
    }
}
