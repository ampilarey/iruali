<?php

namespace App\Console\Commands;

use App\Services\PointsService;
use Illuminate\Console\Command;

/**
 * Monthly: expire loyalty points older than Settings → points_expire_months (0 = never) and warn
 * customers whose points go next month. Scheduled in routes/console.php.
 */
class ExpireLoyaltyPoints extends Command
{
    protected $signature = 'rewards:expire-points {--remind-only : Only send the one-month warnings}';

    protected $description = 'Expire old loyalty points (FIFO) and email customers a month before theirs expire';

    public function handle(PointsService $points): int
    {
        if ($points->expiryMonths() <= 0) {
            $this->info('Points never expire (points_expire_months is 0).');

            return self::SUCCESS;
        }

        $expired = $this->option('remind-only') ? 0 : $points->expireOld();
        $reminded = $points->remindExpiring();

        $this->info("Expired points for {$expired} customer(s); warned {$reminded}.");

        return self::SUCCESS;
    }
}
