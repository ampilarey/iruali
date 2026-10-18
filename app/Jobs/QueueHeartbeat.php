<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

/**
 * Queued every five minutes: when it runs, the queue worker is alive (ops:watch checks the time).
 */
class QueueHeartbeat implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        Cache::forever('queue.heartbeat', now()->timestamp);
    }
}
