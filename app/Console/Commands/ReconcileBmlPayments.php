<?php

namespace App\Console\Commands;

use App\Models\PaymentTransaction;
use App\Services\BmlConnect;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Every 5 minutes: ask BML about card payments that are still open (not confirmed, not failed), so a
 * payment is recorded even when both the customer's return and BML's webhook were lost. Payments
 * from the last 3 days, a few minutes old (the webhook usually comes first). A payment that turns out
 * confirmed for an order that was cancelled meanwhile is flagged as a refund due (PaymentService::
 * syncBml), which alerts the team.
 */
class ReconcileBmlPayments extends Command
{
    protected $signature = 'payments:reconcile-bml {--days=3 : How far back to look}';

    protected $description = 'Check open BML card payments with BML and record the ones that went through';

    public function handle(PaymentService $payments, BmlConnect $bml): int
    {
        if (! $bml->enabled()) {
            $this->line('BML is not set up; nothing to check.');

            return self::SUCCESS;
        }

        $checked = $confirmed = 0;
        PaymentTransaction::where('gateway', 'bml')
            ->whereNotNull('transaction_id')
            ->whereNull('confirmed_at')
            ->where(fn ($q) => $q->whereNull('state')->orWhereNotIn('state', array_merge(PaymentTransaction::FINAL_FAILED, ['CONFIRMED', 'MISMATCH', 'DUPLICATE'])))
            ->where('created_at', '>=', now()->subDays(max(1, (int) $this->option('days'))))
            ->where('created_at', '<=', now()->subMinutes(3))
            ->orderBy('id')
            ->limit(200)
            ->each(function (PaymentTransaction $transaction) use ($payments, &$checked, &$confirmed) {
                $checked++;
                try {
                    if ($payments->syncBml($transaction)->confirmed_at) {
                        $confirmed++;
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $this->info("Checked {$checked} open card payment(s); {$confirmed} went through.");

        return self::SUCCESS;
    }
}
