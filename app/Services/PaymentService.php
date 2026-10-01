<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Notifications\RefundDue;
use App\Notifications\RefundRecorded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Payments are taken by card through BML Connect only.
 *
 * Statuses: unpaid → paid. Older orders may carry other methods (cash on delivery, bank transfer);
 * admins can still mark those as paid.
 */
class PaymentService
{
    /**
     * Payment methods offered at checkout: card payment through BML only, once it is configured.
     * Until then checkout is closed.
     */
    public function methods(): array
    {
        return $this->bml()->enabled() ? ['bml' => __('Card payment (BML)')] : [];
    }

    public static function methodLabel(?string $method): string
    {
        // BML card is the only payment method; older orders may still carry a
        // retired method name, which is shown as-is rather than mislabelled.
        return match ($method) {
            'bml', null, '' => __('Card payment (BML)'),
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    public function canPayOnline(Order $order): bool
    {
        return $order->payment_method === 'bml'
            && $order->payment_status !== 'paid'
            && $order->status === 'pending'
            && $this->bml()->enabled();
    }

    /**
     * Start a BML Connect payment for the order and return the page to send the customer to.
     */
    public function startBmlPayment(Order $order): string
    {
        $attempt = $order->paymentTransactions()->count() + 1;
        $localId = BmlConnect::localId($order->order_number.'P'.$attempt);

        $transaction = $order->paymentTransactions()->create([
            'gateway' => 'bml',
            'local_id' => $localId,
            'amount' => BmlConnect::toLaari($order->total_amount),
            'currency' => 'MVR',
        ]);

        $remote = $this->bml()->createTransaction([
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'localId' => $localId,
            'customerReference' => 'iruali '.$order->order_number,
            'redirectUrl' => route('payments.bml.return', $order),
            'webhook' => route('payments.bml.webhook'),
            'paymentPortalExperience' => $this->bml()->portalExperience(),
        ]);

        $transaction->update([
            'transaction_id' => $remote['id'],
            'state' => $remote['state'] ?? 'INITIATED',
            'payment_url' => $remote['url'],
            'response' => $remote,
        ]);

        return $remote['url'];
    }

    /**
     * Ask BML for the real state of a transaction and apply it. Safe to call any number of times.
     * A confirmed payment only counts when BML's amount, currency and reference match ours.
     */
    public function syncBml(PaymentTransaction $transaction): PaymentTransaction
    {
        $remote = $this->bml()->getTransaction($transaction->transaction_id);

        $matches = (int) ($remote['amount'] ?? -1) === $transaction->amount
            && strtoupper((string) ($remote['currency'] ?? '')) === $transaction->currency
            && (! isset($remote['localId']) || $remote['localId'] === $transaction->local_id);

        $state = strtoupper((string) ($remote['state'] ?? $transaction->state));
        if ($state === 'CONFIRMED' && ! $matches) {
            report(new \RuntimeException("BML transaction {$transaction->transaction_id} is confirmed but does not match order {$transaction->order_id}"));
            $state = 'MISMATCH';
        }

        // The customer's return and BML's webhook can arrive together: lock so the order is confirmed once.
        DB::transaction(function () use ($transaction, $state, $remote) {
            $locked = PaymentTransaction::whereKey($transaction->id)->lockForUpdate()->first();
            $order = Order::whereKey($locked->order_id)->lockForUpdate()->first();
            $locked->update(['state' => $state, 'response' => $remote]);

            if ($state !== 'CONFIRMED' || $locked->confirmed_at) {
                return;
            }
            $locked->update(['confirmed_at' => now()]);
            $amount = $locked->amount / 100;

            if ($order->status === 'cancelled') {
                // Paid after the order was cancelled (its stock is already released): the money goes back.
                $this->flagRefund($order, $amount, 'Paid after the order was cancelled');
            } elseif ($order->payment_status === 'paid') {
                // A second successful payment for the same order.
                $locked->update(['state' => 'DUPLICATE']);
                $this->flagRefund($order, $amount, 'Paid twice');
            } else {
                $this->confirm($order);
            }
        });

        $transaction->refresh();

        return $transaction;
    }

    protected function bml(): BmlConnect
    {
        return app(BmlConnect::class);
    }

    /**
     * Record that the customer is owed money and tell the admins. Adds to any refund already due.
     */
    public function flagRefund(Order $order, float $amount, string $reason): void
    {
        $due = $order->refund_status === 'due' ? (float) $order->refund_amount : 0;
        $order->forceFill([
            'refund_status' => 'due',
            'refund_amount' => round($due + $amount, 2),
            'refund_reason' => $reason,
        ])->save();
        \App\Support\Audit::record('refund.flagged', $order, ['amount' => $amount, 'total_due' => $order->refund_amount, 'reason' => $reason, 'order_number' => $order->order_number]);

        DB::afterCommit(function () use ($order) {
            try {
                if ($email = trim((string) Setting::get('contact_email'))) {
                    Notification::route('mail', $email)->notify(new RefundDue($order->fresh()));
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * The refund has been sent (BML portal or bank transfer): record the reference and tell the customer.
     */
    public function markRefunded(Order $order, string $reference): bool
    {
        if ($order->refund_status !== 'due') {
            return false;
        }

        $order->forceFill(['refund_status' => 'refunded', 'refund_reference' => $reference, 'refunded_at' => now()])->save();
        \App\Support\Audit::record('refund.recorded', $order, ['amount' => $order->refund_amount, 'reference' => $reference, 'order_number' => $order->order_number]);

        try {
            $order->user?->notify(new RefundRecorded($order));
        } catch (Throwable $e) {
            report($e);
        }

        return true;
    }

    public function confirm(Order $order): void
    {
        $order->update(['payment_status' => 'paid', 'paid_at' => now()]);

        app(OrderService::class)->awardRewards($order->fresh());
        app(OrderNotifier::class)->paymentUpdated($order);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'paid' => __('Paid'),
            default => __('Unpaid'),
        };
    }

    public static function statusBadge(?string $status): string
    {
        return match ($status) {
            'paid' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-700',
        };
    }
}
