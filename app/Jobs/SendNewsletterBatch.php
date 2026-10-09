<?php

namespace App\Jobs;

use App\Models\NewsletterIssue;
use App\Services\NewsletterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * One batch of a newsletter send (see NewsletterService::sendBatch). Safe to retry: rows already
 * claimed by an earlier try are never sent again.
 */
class SendNewsletterBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    /**
     * @param  list<int>  $deliveryIds
     */
    public function __construct(public int $issueId, public array $deliveryIds) {}

    public function handle(NewsletterService $newsletters): void
    {
        $issue = NewsletterIssue::find($this->issueId);
        if (! $issue || $issue->isDraft()) {
            return;
        }

        $newsletters->sendBatch($issue, $this->deliveryIds);
    }
}
