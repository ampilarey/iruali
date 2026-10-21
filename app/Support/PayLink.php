<?php

namespace App\Support;

use App\Models\Order;
use App\Services\BmlConnect;
use App\Services\ManualPaymentService;
use Carbon\CarbonInterface;
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

    /**
     * The signed link, working for HOURS hours or until $until (the balance of a booking-fee
     * pre-order is given until a week after it is due: BookingFeeService).
     */
    public static function url(Order $order, ?CarbonInterface $until = null): string
    {
        return URL::temporarySignedRoute('pay.show', $until ?? now()->addHours(self::HOURS), ['order' => $order->getKey()]);
    }

    /**
     * Still waiting for money: an unpaid pending order, or the balance of a booking-fee pre-order
     * whose stock has arrived (Order::awaitingPayment()).
     */
    public static function payable(Order $order): bool
    {
        return $order->awaitingPayment() && $order->cardAmount() > 0;
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
