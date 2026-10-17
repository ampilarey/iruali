<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once a month when the SMS monthly limit (Admin → SMS) stops order updates by SMS.
 */
class SmsLimitReached extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public int $sent, public int $limit) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[iruali] Monthly SMS limit reached');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ops.sms-limit-reached');
    }
}
