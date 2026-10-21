<?php

namespace App\Console\Commands;

use App\Services\BookingFeeService;
use Illuminate\Console\Command;

/**
 * Daily: pre-orders with a booking fee whose stock has arrived and whose balance is not paid. The
 * customer gets one reminder (SMS and email, with a fresh payment link) two days before the due
 * date; past it the team is told once, and Admin → Inbox lists the order until it is paid,
 * cancelled or given more time (App\Services\BookingFeeService).
 */
class BookingBalanceReminders extends Command
{
    protected $signature = 'preorders:balance-reminders';

    protected $description = 'Remind customers to pay the balance of booking-fee pre-orders, and flag overdue ones to the team';

    public function handle(BookingFeeService $bookingFees): int
    {
        $result = $bookingFees->sendReminders();

        $this->info("Sent {$result['reminded']} balance reminder(s); {$result['overdue']} balance(s) newly overdue.");

        return self::SUCCESS;
    }
}
