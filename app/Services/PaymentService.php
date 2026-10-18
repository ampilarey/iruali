<?php

namespace App\Services;

use App\Enums\PaymentStatus;
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
    /**
     * The ways to pay offered at checkout, in order: card (BML set up and switched on), bank
     * transfer and cash on delivery (when an admin switched them on, ManualPaymentService).
     *
     * @return array<string, string>
     */
    public function methods(): array
    {
        return array_filter([
            'bml' => ManualPaymentService::cardEnabled() && $this->bml()->enabled() ? __('Card payment (BML)') : null,
            ManualPaymentService::TRANSFER => ManualPaymentService::transferEnabled() ? __('Bank transfer') : null,
            ManualPaymentService::COD => ManualPaymentService::codEnabled() ? __('Cash on delivery') : null,
            // Pay later: only for business buyers an admin approved (CreditService)
            CreditService::METHOD => app(CreditService::class)->offered(auth()->user()) ? __('Pay later (business invoice)') : null,
        ]);
    }

    public static function methodLabel(?string $method): string
    {
        // BML card is the only payment method; older orders may still carry a
        // retired method name, which is shown as-is rather than mislabelled.
        return match ($method) {
            'bml', null, '' => __('Card payment (BML)'),
            'wallet' => __('Wallet (store credit)'),
            ManualPaymentService::TRANSFER => __('Bank transfer'),
            ManualPaymentService::COD => __('Cash on delivery'),
            CreditService::METHOD => __('Pay later (business invoice)'),
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    public function canPayOnline(Order $order): bool
    {
        return $order->payment_method === 'bml'
            && $order->payment_status !== 'paid'
            && $order->status === 'pending'
            && ManualPaymentService::cardEnabled()
            && $this->bml()->enabled();
    }

    /**
     * Start a BML Connect payment for the order and return the page to send the customer to.
     */
    public function startBmlPayment(Order $order): string
    {
        // The browser that started the payment may be sent back to the order. Anyone else who
        // guesses the return URL is not.
        session()->put(self::returnSessionKey($order), true);

        $attempt = $order->paymentTransactions()->count() + 1;
        $localId = BmlConnect::localId($order->order_number.'P'.$attempt);

        $transaction = $order->paymentTransactions()->create([
            'gateway' => 'bml',
            'local_id' => $localId,
            'amount' => BmlConnect::toLaari($order->cardAmount()), // the total less whatever the wallet paid
            'currency' => 'MVR',
        ]);

        $remote = $this->bml()->createTransaction([
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'localId' => $localId,
            'customerReference' => 'iruali '.$order->order_number,
            // A guest's code rides in the path (BML appends its own ?transactionId=...), so a guest who
            // finishes paying in another browser still gets back to the order
            'redirectUrl' => route('payments.bml.return', array_filter(['order' => $order, 'token' => $order->guest_token])),
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

    public static function returnSessionKey(Order $order): string
    {
        return 'bml_return.'.$order->getKey();
    }

    /**
     * The customer coming back from BML, or the account that owns the order. Not an id guess.
     */
    public function visitorMaySeeReturn(\Illuminate\Http\Request $request, Order $order): bool
    {
        if ($request->user() && (int) $order->user_id === (int) $request->user()->id) {
            return true;
        }

        $token = (string) ($request->route('token') ?? $request->query('token', ''));
        if ($order->guest_token && $token !== '' && hash_equals((string) $order->guest_token, $token)) {
            return true;
        }

        return (bool) $request->session()->get(self::returnSessionKey($order));
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
        \App\Support\TeamAlerts::send('refund_due', 'iruali: refund due '.\App\Support\Money::format($amount).' on '.$order->order_number.' ('.$reason.').', route('admin.orders.show', $order));

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

        app(OrderNotifier::class)->sendToCustomer($order, new RefundRecorded($order));

        return true;
    }

    public function confirm(Order $order): void
    {
        $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
        FunnelService::orderPaid($order);
        app(GstService::class)->orderPaid($order); // each shop's invoice number, once the payment is committed

        app(OrderService::class)->awardRewards($order->fresh());
        app(GiftCardService::class)->issueForOrder($order); // a paid gift-card order sends the card
        app(OrderNotifier::class)->paymentUpdated($order);
    }

    public static function statusLabel(?string $status): string
    {
        return PaymentStatus::labelFor($status);
    }

    public static function statusBadge(?string $status): string
    {
        return PaymentStatus::badgeFor($status);
    }
}
