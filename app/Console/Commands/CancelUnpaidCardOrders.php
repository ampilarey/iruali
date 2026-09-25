<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Card orders hold stock from the moment they are placed. If the customer never pays,
 * cancel the order (which puts the stock back) — after asking BML one last time.
 */
class CancelUnpaidCardOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid-card {--hours=24 : Cancel card orders still unpaid after this many hours}';

    protected $description = 'Cancel card-payment orders that were never paid and release their stock';

    public function handle(PaymentService $payments, OrderService $orders): int
    {
        $cutoff = now()->subHours(max(1, (int) $this->option('hours')));
        $cancelled = 0;

        Order::where('payment_method', 'bml')
            ->where('payment_status', '!=', 'paid')
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->with('paymentTransactions')
            ->each(function (Order $order) use ($payments, $orders, &$cancelled) {
                foreach ($order->paymentTransactions->whereNotNull('transaction_id') as $transaction) {
                    try {
                        $payments->syncBml($transaction);
                    } catch (Throwable $e) {
                        report($e);

                        return; // can't be sure it's unpaid; try again next run
                    }
                }

                if ($order->fresh()->payment_status !== 'paid' && $orders->updateOrderStatus($order->fresh(), 'cancelled')) {
                    $cancelled++;
                }
            });

        $this->info("Cancelled {$cancelled} unpaid card order(s).");

        return self::SUCCESS;
    }
}
