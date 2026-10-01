<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Result of `php artisan backup:restore-drill` (monthly): did the newest backup restore, and
 * how its row counts compare with the live database.
 *
 * @phpstan-type Report array{ok: bool, disk: ?string, backup: ?string, drill_database: ?string, tables: int, counts: array<string, array{backup: ?int, live: int}>, steps: string[], error: ?string}
 */
class RestoreDrillReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param  Report  $report */
    public function __construct(public array $report)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[iruali] Backup restore drill '.($this->report['ok'] ? 'PASSED' : 'FAILED'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ops.restore-drill-report');
    }
}
