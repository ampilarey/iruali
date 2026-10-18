<?php

namespace App\Services;

use App\Models\CreditAccount;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pay later for business buyers an admin approved (customer page → Pay later): the order goes to
 * the shops straight away like cash on delivery, an invoice is due after the account's terms, and
 * the customer is reminded before it is due and told when it is overdue (credit:reminders). Shops
 * are paid out once the invoice is paid, as with any unpaid order.
 */
class CreditService
{
    public const METHOD = 'credit';

    public const REMIND_DAYS_BEFORE = 3;

    public function account(?User $user): ?CreditAccount
    {
        return $user ? CreditAccount::where('user_id', $user->id)->first() : null;
    }

    /**
     * Unpaid pay-later orders of this customer.
     *
     * @return Builder<Order>
     */
    public function openInvoices(User $user): Builder
    {
        return Order::where('user_id', $user->id)->where('payment_method', self::METHOD)
            ->where('payment_status', '!=', 'paid')->where('status', '!=', 'cancelled');
    }

    public function outstanding(User $user): float
    {
        return round((float) $this->openInvoices($user)->sum('total_amount'), 2);
    }

    public function available(User $user): float
    {
        $account = $this->account($user);

        return $account && $account->isActive() ? max(0, round((float) $account->credit_limit - $this->outstanding($user), 2)) : 0.0;
    }

    public function hasOverdue(User $user): bool
    {
        return $this->openInvoices($user)->whereDate('credit_due_on', '<', today())->exists();
    }

    /**
     * Why this customer can't pay this amount later, or null.
     */
    public function problem(?User $user, float $amount): ?string
    {
        $account = $this->account($user);
        if (! $user || ! $account || ! $account->isActive()) {
            return __('Pay later is not available on this account.');
        }
        if ($this->hasOverdue($user)) {
            return __('Please pay your overdue invoices before ordering on pay later again.');
        }
        if ($amount > $this->available($user)) {
            return __('This order is more than your pay-later credit left (:amount).', ['amount' => \App\Support\Money::format($this->available($user))]);
        }

        return null;
    }

    /**
     * Whether to offer pay later at checkout.
     */
    public function offered(?User $user): bool
    {
        $account = $this->account($user);

        return $account !== null && $account->isActive();
    }

    public function dueDate(User $user): \Illuminate\Support\Carbon
    {
        return today()->addDays((int) ($this->account($user)->terms_days ?? 30));
    }
}
