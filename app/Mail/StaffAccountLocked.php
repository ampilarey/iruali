<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Sent to the alerts inbox when a staff account is locked after repeated wrong sign-in tries.
 */
class StaffAccountLocked extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $email, public string $ip, public Carbon $until) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[iruali] Staff account locked: '.$this->email);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ops.staff-locked');
    }
}
