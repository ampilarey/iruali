<?php

namespace App\Console\Commands;

use App\Mail\ErrorDigest;
use App\Models\ErrorEvent;
use App\Support\ErrorTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ErrorsDigest extends Command
{
    protected $signature = 'errors:digest {--hours=24 : Look back this many hours}';

    protected $description = 'Email a summary of new and frequent errors (only when there is something to report)';

    public function handle(): int
    {
        $since = now()->subHours((int) $this->option('hours'));

        $new = ErrorEvent::unresolved()->where('first_seen_at', '>=', $since)->orderByDesc('count')->get();
        $top = ErrorEvent::unresolved()->where('last_seen_at', '>=', $since)->orderByDesc('count')->limit(10)->get();

        if ($new->isEmpty() && $top->isEmpty()) {
            $this->info('Nothing to report.');

            return self::SUCCESS;
        }

        $to = ErrorTracker::alertsTo();
        if (! $to) {
            $this->warn('No address to send the digest to: set ALERTS_EMAIL in .env or a contact email in Admin → Settings.');

            return self::FAILURE;
        }

        Mail::to($to)->send(new ErrorDigest($new, $top, ErrorEvent::unresolved()->count()));
        $this->info("Digest sent to {$to}: {$new->count()} new, {$top->count()} frequent.");

        return self::SUCCESS;
    }
}
