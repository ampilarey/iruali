<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Notifications\CreditInvoice;
use App\Services\CreditService;
use App\Support\TeamAlerts;
use Illuminate\Console\Command;

/**
 * Daily: pay-later invoices due in a few days get one reminder; those past their due date are
 * marked overdue, the customer is told and the team gets one alert.
 */
class CreditReminders extends Command
{
    protected $signature = 'credit:reminders';

    protected $description = 'Remind business buyers of pay-later invoices due soon and mark overdue ones';

    public function handle(): int
    {
        $open = Order::with('user')->where('payment_method', CreditService::METHOD)->where('payment_status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')->whereNotNull('credit_due_on');

        $due = (clone $open)->whereNull('credit_reminded_at')->whereDate('credit_due_on', '>=', today())
            ->whereDate('credit_due_on', '<=', today()->addDays(CreditService::REMIND_DAYS_BEFORE))->get();
        foreach ($due as $order) {
            $order->user?->notify(new CreditInvoice($order, 'due'));
            $order->forceFill(['credit_reminded_at' => now()])->save();
        }

        $overdue = (clone $open)->whereNull('credit_overdue_at')->whereDate('credit_due_on', '<', today())->get();
        foreach ($overdue as $order) {
            $order->user?->notify(new CreditInvoice($order, 'overdue'));
            $order->forceFill(['credit_overdue_at' => now()])->save();
        }
        if ($overdue->isNotEmpty()) {
            TeamAlerts::send('late_order', 'iruali: '.$overdue->count().' pay-later invoice(s) now overdue: '.$overdue->pluck('order_number')->take(5)->implode(', ').'.', route('admin.inbox'));
        }

        $this->info('Reminded: '.$due->count().'; overdue: '.$overdue->count().'.');

        return self::SUCCESS;
    }
}
