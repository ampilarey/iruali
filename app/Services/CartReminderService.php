<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartReminder;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Voucher;
use App\Notifications\CartReminder as CartReminderNotification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Abandoned-cart emails. A signed-in customer who leaves items in the cart gets one nudge after
 * three hours and a second after 48 hours, the second with a single-use voucher when the owner
 * has set a percentage in Settings. Guests, customers who opted out, and carts that were followed
 * by an order are left alone.
 */
class CartReminderService
{
    public const FIRST_AFTER_HOURS = 3;

    public const SECOND_AFTER_HOURS = 48;

    public const VOUCHER_DAYS = 7;

    /**
     * Send every reminder that is due. Returns how many were sent per stage.
     *
     * @return array{1: int, 2: int}
     */
    public function sendDue(): array
    {
        $sent = [1 => 0, 2 => 0];
        if (! $this->enabled()) {
            return $sent;
        }

        foreach ([1 => self::FIRST_AFTER_HOURS, 2 => self::SECOND_AFTER_HOURS] as $stage => $hours) {
            $this->dueCarts($stage, now()->subHours($hours))->each(function (Cart $cart) use ($stage, &$sent) {
                if ($this->send($cart, $stage)) {
                    $sent[$stage]++;
                }
            });
        }

        return $sent;
    }

    public function enabled(): bool
    {
        return filter_var(Setting::get('abandoned_cart_emails_enabled'), FILTER_VALIDATE_BOOL);
    }

    public function voucherPercent(): float
    {
        return max(0, min(100, (float) Setting::get('abandoned_cart_voucher_percent')));
    }

    /**
     * Carts owned by a customer, with items, untouched since the cutoff, not yet reminded at this stage.
     */
    protected function dueCarts(int $stage, \DateTimeInterface $cutoff)
    {
        return Cart::query()
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->where('updated_at', '<', $cutoff)
            ->whereHas('items')
            ->whereDoesntHave('items', fn ($q) => $q->where('updated_at', '>=', $cutoff))
            ->whereDoesntHave('reminders', fn ($q) => $q->where('stage', $stage))
            ->when($stage === 2, fn ($q) => $q->whereHas('reminders', fn ($r) => $r->where('stage', 1)))
            ->whereHas('user', fn ($q) => $q->whereNull('marketing_opt_out_at')->whereNotNull('email'))
            ->with(['user', 'items.product.mainImage', 'items.variant'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Send one reminder, unless the customer ordered since they last touched the cart.
     */
    public function send(Cart $cart, int $stage): bool
    {
        $user = $cart->user;
        if (! $user || ! $user->email || $user->marketing_opt_out_at) {
            return false;
        }

        $lastActivity = max($cart->updated_at, $cart->items->max('updated_at') ?? $cart->updated_at);
        if (Order::where('user_id', $user->id)->where('created_at', '>=', $lastActivity)->exists()) {
            return false;
        }

        $voucher = $stage === 2 ? $this->makeVoucher($user->id) : null;

        $reminder = CartReminder::create([
            'cart_id' => $cart->id,
            'stage' => $stage,
            'sent_at' => now(),
            'voucher_id' => $voucher?->id,
        ]);

        try {
            $user->notify(new CartReminderNotification($cart, $stage, $voucher));
        } catch (Throwable $e) {
            report($e);
            $reminder->delete();
            $voucher?->delete();

            return false;
        }

        return true;
    }

    /**
     * A percentage voucher only this customer may use, valid for a week.
     */
    protected function makeVoucher(int $userId): ?Voucher
    {
        $percent = $this->voucherPercent();
        if ($percent <= 0) {
            return null;
        }

        do {
            $code = 'BACK-'.strtoupper(Str::random(6));
        } while (Voucher::where('code', $code)->exists());

        return Voucher::create([
            'code' => $code,
            'type' => 'percent',
            'amount' => $percent,
            'max_uses' => 1,
            'used_count' => 0,
            'valid_from' => now(),
            'valid_until' => now()->addDays(self::VOUCHER_DAYS),
            'is_active' => true,
            'user_id' => $userId,
        ]);
    }
}
