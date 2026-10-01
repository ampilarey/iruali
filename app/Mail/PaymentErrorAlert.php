<?php

namespace App\Mail;

use App\Models\ErrorEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent at once (at most once an hour per error) when something in the BML payment code throws.
 */
class PaymentErrorAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ErrorEvent $event)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[iruali] Payment error: '.$this->event->shortClass().' ('.$this->event->count.'x)');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ops.payment-error-alert');
    }
}
