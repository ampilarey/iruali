<?php

namespace App\Console\Commands;

use App\Services\PreorderService;
use Illuminate\Console\Command;

/**
 * Daily: pre-orders still waiting for their stock more than a week after their expected date are
 * flagged, and show in Admin → Inbox as "Late pre-orders" until their stock arrives, the shop moves
 * the date or the order is cancelled.
 */
class FlagLatePreorders extends Command
{
    protected $signature = 'preorders:flag-late';

    protected $description = 'Flag pre-orders more than 7 days past their expected date for Admin → Inbox';

    public function handle(PreorderService $preorders): int
    {
        $flagged = $preorders->flagLate();
        $orders = $preorders->lateOrderCount();

        $this->info("Flagged {$flagged} late pre-order line(s); {$orders} order(s) with late pre-orders are in the admin inbox.");

        return self::SUCCESS;
    }
}
