<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Store credit. Every movement goes through credit()/debit(), which lock the customer's row so the
 * balance can never go below zero and always equals the sum of wallet_transactions.
 */
class WalletService
{
    public function balance(User $user): float
    {
        return round((float) User::whereKey($user->id)->value('wallet_balance'), 2);
    }

    public function credit(User $user, float $amount, string $type, array $extra = []): WalletTransaction
    {
        return $this->move($user, abs(round($amount, 2)), $type, $extra);
    }

    /**
     * Take money out. Throws when the wallet does not hold enough.
     */
    public function debit(User $user, float $amount, string $type, array $extra = []): WalletTransaction
    {
        return $this->move($user, -abs(round($amount, 2)), $type, $extra);
    }

    protected function move(User $user, float $amount, string $type, array $extra): WalletTransaction
    {
        if (! in_array($type, WalletTransaction::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown wallet transaction type {$type}");
        }
        if ($amount == 0) {
            throw new \InvalidArgumentException('A wallet movement needs an amount');
        }

        return DB::transaction(function () use ($user, $amount, $type, $extra) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            $balance = round((float) $locked->wallet_balance + $amount, 2);
            if ($balance < 0) {
                throw new RuntimeException(__('Not enough wallet balance.'));
            }
            User::whereKey($user->id)->update(['wallet_balance' => $balance]);
            $user->wallet_balance = $balance;
            $user->syncOriginalAttribute('wallet_balance');

            return WalletTransaction::create(array_merge(['user_id' => $user->id, 'amount' => $amount, 'type' => $type], $extra));
        });
    }

    // ---- Paying for orders ------------------------------------------------------------------

    /**
     * Pay as much of the order as the wallet holds. Runs inside the checkout transaction, right after
     * the order row is made. Returns the amount taken. When it covers the whole total the order is
     * marked as a wallet payment (OrderService confirms it after the commit).
     */
    public function payFromWallet(Order $order, User $user): float
    {
        $balance = $this->balance($user);
        $total = round((float) $order->total_amount, 2);
        $amount = round(min($balance, $total), 2);
        if ($amount <= 0) {
            return 0.0;
        }

        $this->debit($user, $amount, 'purchase', ['order_id' => $order->id, 'reference' => $order->order_number, 'note' => __('Order :number', ['number' => $order->order_number])]);

        $full = $amount >= $total;
        $order->forceFill(['wallet_amount' => $amount, 'payment_method' => $full ? 'wallet' : $order->payment_method])->save();

        if ($full) {
            PaymentTransaction::create([
                'order_id' => $order->id,
                'gateway' => 'wallet',
                'local_id' => BmlConnect::localId($order->order_number.'W'),
                'transaction_id' => 'wallet-'.$order->id,
                'amount' => BmlConnect::toLaari($amount),
                'currency' => 'MVR',
                'state' => 'CONFIRMED',
                'confirmed_at' => now(),
            ]);
        }

        return $amount;
    }

    /**
     * An order is being cancelled: whatever the wallet paid goes back to it (once). A refund-due
     * flag for the card part is raised by OrderService; the wallet part never needs one.
     */
    public function reverseOrderPayment(Order $order): float
    {
        $amount = round((float) $order->wallet_amount, 2);
        if ($amount <= 0 || $order->wallet_refunded_at || ! $order->user) {
            return 0.0;
        }

        $this->credit($order->user, $amount, 'refund', ['order_id' => $order->id, 'reference' => $order->order_number, 'note' => __('Order :number cancelled', ['number' => $order->order_number])]);
        $order->forceFill(['wallet_refunded_at' => now()])->save();

        return $amount;
    }

    // ---- Refunds to the wallet (admin's choice instead of the card) ------------------------------

    /**
     * Settle a refund that is due on an order by crediting the wallet instead of the card.
     */
    public function refundOrderToWallet(Order $order): bool
    {
        if ($order->refund_status !== 'due' || ! $order->user || (float) $order->refund_amount <= 0) {
            return false;
        }

        return DB::transaction(function () use ($order) {
            $row = $this->credit($order->user, (float) $order->refund_amount, 'refund', ['order_id' => $order->id, 'note' => __('Refund for order :number', ['number' => $order->order_number])]);
            $row->update(['reference' => 'WALLET-'.$row->id]);

            return app(PaymentService::class)->markRefunded($order, 'WALLET-'.$row->id);
        });
    }

    /**
     * Settle an approved return by crediting the wallet.
     */
    public function refundReturnToWallet(ReturnRequest $return): bool
    {
        $user = $return->user ?? $return->order?->user;
        if ($return->status !== 'approved' || ! $user || (float) $return->refund_amount <= 0) {
            return false;
        }

        return DB::transaction(function () use ($return, $user) {
            $row = $this->credit($user, (float) $return->refund_amount, 'refund', ['order_id' => $return->order_id, 'note' => __('Return refund for order :number', ['number' => $return->order->order_number ?? '#'.$return->order_id])]);
            $row->update(['reference' => 'WALLET-'.$row->id]);

            return app(ReturnService::class)->markRefunded($return, 'WALLET-'.$row->id);
        });
    }
}
