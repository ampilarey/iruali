<?php

namespace App\Console\Commands;

use App\Services\ManualPaymentService;
use Illuminate\Console\Command;

/**
 * Bank transfer orders hold stock from the moment they are placed. If no transfer slip comes within
 * the hours set (Admin → Settings → Payment methods), cancel the order, which puts the stock back.
 * Orders with a slip wait for an admin to confirm the payment. A pre-order paid once the shop
 * confirms waits until its payment is due (PreorderConfirmService).
 */
class CancelUnpaidTransferOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid-transfer';

    protected $description = 'Cancel bank transfer orders with no transfer slip after the hours allowed';

    public function handle(ManualPaymentService $payments): int
    {
        $this->info('Cancelled '.$payments->cancelUnpaidTransfers().' unpaid bank transfer order(s).');

        return self::SUCCESS;
    }
}
