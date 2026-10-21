<?php

namespace App\Console\Commands;

use App\Services\BookingFeeService;
use App\Services\PreorderConfirmService;
use Illuminate\Console\Command;

/**
 * Daily: pre-orders with a booking fee whose stock has arrived and whose balance is not paid. The
 * customer gets one reminder (SMS and email, with a fresh payment link) two days before the due
 * date; past it the team is told once, and Admin → Inbox lists the order until it is paid,
 * cancelled or given more time (App\Services\BookingFeeService). Also pre-orders paid once the shop
 * confirms: one reminder the day before payment is due (App\Services\PreorderConfirmService).
 */
class BookingBalanceReminders extends Command
{
    protected $signature = 'preorders:balance-reminders';

    protected $description = 'Remind customers to pay for pre-orders (booking-fee balances and confirmed pre-orders), and flag overdue balances to the team';

    public function handle(BookingFeeService $bookingFees, PreorderConfirmService $confirms): int
    {
        $result = $bookingFees->sendReminders();
        $confirmed = $confirms->sendReminders();

        $this->info("Sent {$result['reminded']} balance reminder(s); {$result['overdue']} balance(s) newly overdue; {$confirmed} confirmed pre-order payment reminder(s).");

        return self::SUCCESS;
    }
}
