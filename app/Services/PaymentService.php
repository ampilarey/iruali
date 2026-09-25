<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Payment methods: card payment through BML Connect, cash on delivery and bank transfer with slips.
 *
 * Statuses: unpaid → submitted (slip uploaded) → paid, or → rejected (customer uploads again).
 * Slips are stored on the private "local" disk and only streamed to the customer and admins.
 */
class PaymentService
{
    public const DISK = 'local';

    /**
     * Payment methods offered at checkout, card payment (BML) first once it is configured.
     */
    public function methods(): array
    {
        $methods = [];

        if ($this->bml()->enabled()) {
            $methods['bml'] = __('Card payment (BML)');
        }

        if (Setting::get('payment_cod_enabled', '1') !== '0' || $methods === []) {
            $methods['cod'] = __('Cash on delivery');
        }

        if ($this->bankTransferEnabled()) {
            $methods['bank_transfer'] = __('Bank transfer');
        }

        return $methods;
    }

    public static function methodLabel(?string $method): string
    {
        return match ($method) {
            'bml' => __('Card payment (BML)'),
            'bank_transfer' => __('Bank transfer'),
            default => __('Cash on delivery'),
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
        $localId = $order->order_number.'-'.$attempt;

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
        \Illuminate\Support\Facades\DB::transaction(function () use ($transaction, $state, $remote) {
            $locked = PaymentTransaction::whereKey($transaction->id)->lockForUpdate()->first();
            $locked->update(['state' => $state, 'response' => $remote]);

            if ($state === 'CONFIRMED' && ! $locked->confirmed_at) {
                $locked->update(['confirmed_at' => now()]);
                $order = $locked->order;
                if ($order->payment_status !== 'paid') {
                    $this->confirm($order);
                }
            }
        });

        $transaction->refresh();

        return $transaction;
    }

    protected function bml(): BmlConnect
    {
        return app(BmlConnect::class);
    }

    public function bankTransferEnabled(): bool
    {
        return trim((string) Setting::get('bank_account_number')) !== '';
    }

    public function bankDetails(): array
    {
        return [
            'bank' => Setting::get('bank_name'),
            'name' => Setting::get('bank_account_name'),
            'number' => Setting::get('bank_account_number'),
        ];
    }

    public function canUploadSlip(Order $order): bool
    {
        return $order->payment_method === 'bank_transfer'
            && in_array($order->payment_status, ['unpaid', 'rejected', 'submitted'], true)
            && $order->status !== 'cancelled';
    }

    public function submitSlip(Order $order, UploadedFile $file): void
    {
        if ($order->payment_slip) {
            Storage::disk(self::DISK)->delete($order->payment_slip);
        }

        $path = $file->storeAs(
            'payment-slips',
            $order->order_number.'-'.Str::random(8).'.'.$file->extension(),
            self::DISK
        );

        $order->update(['payment_slip' => $path, 'payment_status' => 'submitted']);

        app(OrderNotifier::class)->slipSubmitted($order);
    }

    public function confirm(Order $order): void
    {
        $order->update(['payment_status' => 'paid', 'paid_at' => now()]);

        app(OrderNotifier::class)->paymentUpdated($order);
    }

    public function reject(Order $order): void
    {
        $order->update(['payment_status' => 'rejected', 'paid_at' => null]);

        app(OrderNotifier::class)->paymentUpdated($order);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'paid' => __('Paid'),
            'submitted' => __('Slip submitted'),
            'rejected' => __('Slip rejected'),
            default => __('Unpaid'),
        };
    }

    public static function statusBadge(?string $status): string
    {
        return match ($status) {
            'paid' => 'bg-green-100 text-green-800',
            'submitted' => 'bg-sun-soft text-sun-ink',
            'rejected' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-700',
        };
    }
}
