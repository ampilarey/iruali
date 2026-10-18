<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The morning refunds summary (refunds:daily-summary).
 */
class RefundsSummary extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(public array $summary) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[iruali] Refunds for '.$this->summary['date']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ops.refunds-summary');
    }
}
