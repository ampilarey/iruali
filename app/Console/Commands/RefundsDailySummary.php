<?php

namespace App\Console\Commands;

use App\Mail\RefundsSummary;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Support\ErrorTracker;
use App\Support\RefundApproval;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Every morning: yesterday's refunds (cancelled orders and returns, card, transfer or wallet), what is
 * still due, and what waits for a second person's approval, to the alerts inbox. Nothing to say: no email.
 */
class RefundsDailySummary extends Command
{
    protected $signature = 'refunds:daily-summary';

    protected $description = 'Email yesterday\'s refunds and the ones still due';

    public function handle(): int
    {
        $to = ErrorTracker::alertsTo();
        if (! $to) {
            $this->warn('No alerts inbox.');

            return self::SUCCESS;
        }

        [$from, $until] = [today()->subDay(), today()];
        $orders = Order::whereBetween('refunded_at', [$from, $until])->get(['order_number', 'refund_amount', 'refund_reason', 'refund_reference']);
        $returns = ReturnRequest::with('order:id,order_number')->whereBetween('refunded_at', [$from, $until])->get(['id', 'order_id', 'refund_amount', 'reason', 'refund_reference']);
        $dueOrders = Order::where('refund_status', 'due')->get(['id', 'refund_amount', 'refund_approved_at']);
        $dueReturns = ReturnRequest::where('status', 'approved')->get(['id', 'refund_amount', 'refund_approved_at']);
        $waiting = $dueOrders->concat($dueReturns)->filter(fn ($r) => RefundApproval::needed($r) && ! RefundApproval::approved($r))->count();

        $summary = [
            'date' => $from->format('j M Y'),
            'refunded' => $orders->map(fn ($o) => ['what' => 'Order '.$o->order_number, 'amount' => (float) $o->refund_amount, 'why' => (string) $o->refund_reason, 'ref' => (string) $o->refund_reference])
                ->concat($returns->map(fn ($r) => ['what' => 'Return #'.$r->id.' ('.$r->order?->order_number.')', 'amount' => (float) $r->refund_amount, 'why' => (string) $r->reason, 'ref' => (string) $r->refund_reference]))->values()->all(),
            'due_count' => $dueOrders->count() + $dueReturns->count(),
            'due_total' => (float) $dueOrders->sum('refund_amount') + (float) $dueReturns->sum('refund_amount'),
            'waiting_approval' => $waiting,
        ];
        if ($summary['refunded'] === [] && $summary['due_count'] === 0) {
            $this->info('Nothing to report.');

            return self::SUCCESS;
        }

        Mail::to($to)->queue(new RefundsSummary($summary));
        $this->info('Summary sent.');

        return self::SUCCESS;
    }
}
