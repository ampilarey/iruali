<?php

namespace App\Console\Commands;

use App\Services\QuoteService;
use Illuminate\Console\Command;

/**
 * Bulk quotes whose "valid until" day has passed and were not ordered are marked expired (hourly).
 * Their cart lines are taken out, with a notice, the next time the customer opens the cart.
 */
class ExpireQuotes extends Command
{
    protected $signature = 'quotes:expire';

    protected $description = 'Mark bulk quotes past their valid-until day as expired';

    public function handle(QuoteService $quotes): int
    {
        $this->info('Expired '.$quotes->expireDue().' quote(s).');

        return self::SUCCESS;
    }
}
