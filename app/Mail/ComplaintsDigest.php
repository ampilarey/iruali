<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To the alerts inbox: the weekly complaints and ratings summary, or the complaints left open
 * without an update (complaints:weekly-summary, complaints:remind).
 *
 * @property array<string, mixed> $summary
 */
class ComplaintsDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  'weekly'|'stale'  $kind
     * @param  array<string, mixed>  $summary
     */
    public function __construct(public string $kind, public array $summary) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->kind === 'weekly' ? '[iruali] Complaints and ratings this week' : '[iruali] Complaints waiting for an answer');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ops.complaints-digest');
    }
}
