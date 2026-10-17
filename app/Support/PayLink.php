<?php

namespace App\Support;

use App\Models\Order;
use App\Services\BmlConnect;
use App\Services\ManualPaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

/**
 * A payment link for an unpaid order (Admin → order → Send payment link): a signed page, with no
 * sign-in, where the customer pays by card or bank transfer, whichever is switched on. It works for
 * 48 hours, and while it does the order is not cancelled for being unpaid.
 */
class PayLink
{
    public const HOURS = 48;

    public static function url(Order $order): string
    {
        return URL::temporarySignedRoute('pay.show', now()->addHours(self::HOURS), ['order' => $order->getKey()]);
    }

    /**
     * Still waiting for money: pending, not paid, not cancelled.
     */
    public static function payable(Order $order): bool
    {
        return $order->status === 'pending' && $order->payment_status !== 'paid' && $order->cardAmount() > 0;
    }

    public static function cardAvailable(): bool
    {
        return ManualPaymentService::cardEnabled() && app(BmlConnect::class)->enabled();
    }

    public static function transferAvailable(): bool
    {
        return ManualPaymentService::transferEnabled();
    }

    /**
     * Leave out orders whose payment link is still working (for the unpaid-order cancel jobs).
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public static function withoutLiveLink(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('payment_link_sent_at')->orWhere('payment_link_sent_at', '<', now()->subHours(self::HOURS)));
    }
}
