<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent by `php artisan iruali:ready --send-test-mail=...`. Deliberately not queued: the point is to
 * prove the SMTP settings work right now, from the command line.
 */
class ReadyCheckTestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'iruali test mail');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>This is a test email from iruali. Mail is configured correctly.</p>');
    }
}
