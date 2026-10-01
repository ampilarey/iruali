<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * The daily 07:00 summary from `php artisan errors:digest`: errors first seen in the last day
 * and the ten most frequent ones. Only sent when there is something in it.
 */
class ErrorDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Collection $new, public Collection $top, public int $unresolved)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[iruali] Error digest: '.$this->new->count().' new, '.$this->unresolved.' unresolved');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ops.error-digest');
    }
}
