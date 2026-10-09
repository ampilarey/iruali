<?php

namespace App\Console\Commands;

use App\Services\WishlistPriceDropService;
use Illuminate\Console\Command;

/**
 * The daily price-drop alert for wishlists (see WishlistPriceDropService). Scheduled once a day at
 * 10:00 Maldives time in routes/console.php.
 */
class SendWishlistPriceDrops extends Command
{
    protected $signature = 'wishlist:price-drops';

    protected $description = 'Tell each customer which wishlisted products got cheaper since they were last told';

    public function handle(WishlistPriceDropService $alerts): int
    {
        $sent = $alerts->sendAll();
        $this->info("Price-drop alerts sent to {$sent} customer(s).");

        return self::SUCCESS;
    }
}
