<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\LoyaltyTiers;
use Illuminate\Console\Command;

/**
 * Nightly: store each customer's loyalty tier again, so tiers go down quietly as old orders pass 12
 * months (moving up is handled when an order is paid, with a message).
 */
class RefreshLoyaltyTiers extends Command
{
    protected $signature = 'rewards:refresh-tiers';

    protected $description = 'Recalculate loyalty tiers from the last 12 months of spending';

    public function handle(): int
    {
        if (! LoyaltyTiers::enabled()) {
            $this->line('Loyalty tiers are switched off.');

            return self::SUCCESS;
        }

        $changed = 0;
        User::whereNotNull('loyalty_tier')->orWhereHas('orders', fn ($q) => $q->where('payment_status', 'paid'))
            ->orderBy('id')
            ->each(function (User $user) use (&$changed) {
                $before = $user->loyalty_tier;
                LoyaltyTiers::refresh($user);
                if ($user->loyalty_tier !== $before) {
                    $changed++;
                }
            });

        $this->info("{$changed} customer tier(s) changed.");

        return self::SUCCESS;
    }
}
