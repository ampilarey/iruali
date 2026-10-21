<?php

namespace App\Console\Commands;

use App\Services\PreorderService;
use Illuminate\Console\Command;

/**
 * Daily: pre-orders still waiting for their stock past their expected date by more than their late
 * days (the product's when the order was placed, a week by default) are flagged, and show in
 * Admin → Inbox as "Late pre-orders" until their stock arrives, the shop moves the date or the order
 * is cancelled. A booking fee on a late order is refunded in full if the customer cancels.
 */
class FlagLatePreorders extends Command
{
    protected $signature = 'preorders:flag-late';

    protected $description = 'Flag pre-orders past their expected date by more than their late days (7 by default) for Admin → Inbox';

    public function handle(PreorderService $preorders): int
    {
        $flagged = $preorders->flagLate();
        $orders = $preorders->lateOrderCount();

        $this->info("Flagged {$flagged} late pre-order line(s); {$orders} order(s) with late pre-orders are in the admin inbox.");

        return self::SUCCESS;
    }
}
