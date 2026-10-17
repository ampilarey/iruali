<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nightly: every customer's points balance must equal the sum of their points ledger. A balance
 * that drifted is set back to the ledger (the ledger is the record) and written to the audit log.
 */
class CheckPointsBalances extends Command
{
    protected $signature = 'rewards:check-balances';

    protected $description = 'Check that points balances match the points ledger and fix any that drifted';

    public function handle(): int
    {
        $ledger = DB::table('points_transactions')->selectRaw('user_id, SUM(points) as total')->groupBy('user_id')->pluck('total', 'user_id');
        $fixed = 0;

        User::where(fn ($q) => $q->where('loyalty_points', '!=', 0)->orWhereIn('id', $ledger->keys()))
            ->select(['id', 'loyalty_points'])
            ->orderBy('id')
            ->each(function (User $user) use ($ledger, &$fixed) {
                $expected = (int) ($ledger[$user->id] ?? 0);
                if ((int) $user->loyalty_points === $expected) {
                    return;
                }
                Audit::record('points.balance_fixed', $user, ['was' => (int) $user->loyalty_points, 'ledger' => $expected]);
                $user->forceFill(['loyalty_points' => $expected])->save();
                $fixed++;
            });

        $this->info($fixed ? "Fixed {$fixed} points balance(s) to match the ledger." : 'All points balances match the ledger.');

        return self::SUCCESS;
    }
}
