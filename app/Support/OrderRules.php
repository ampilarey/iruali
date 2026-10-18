<?php

namespace App\Support;

use App\Models\Cart;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Services\DeliveryService;
use App\Services\ManualPaymentService;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Rules on placing an order, from Admin → Settings → Ordering and each shop's settings:
 * a site-wide minimum order and a shop's own minimum, a small-order fee (part of the delivery
 * charge, kept by iruali), and pausing ordering (everything, delivery only or cash on delivery
 * only), now or for a planned window, with a message and "tell me when it's back".
 */
class OrderRules
{
    public const PAUSE_MODES = ['off', 'all', 'delivery', 'cod'];

    public static function siteMinimum(): float
    {
        return max(0, (float) Setting::get('min_order_amount', 0));
    }

    /**
     * The fee on an order whose goods come to less than the threshold (0 when off).
     */
    public static function smallOrderFee(float $goodsTotal): float
    {
        $fee = max(0, (float) Setting::get('small_order_fee', 0));
        $under = max(0, (float) Setting::get('small_order_threshold', 0));

        return $fee > 0 && $under > 0 && $goodsTotal < $under ? round($fee, 2) : 0.0;
    }

    /**
     * Why the cart can't be ordered yet (below iruali's or a shop's minimum), or null.
     */
    public static function minimumProblem(Cart $cart, float $goodsTotal): ?string
    {
        $minimum = self::siteMinimum();
        if ($minimum > 0 && $goodsTotal < $minimum) {
            return __('The minimum order is :amount. Add :more more to check out.', ['amount' => Money::format($minimum), 'more' => Money::format($minimum - $goodsTotal)]);
        }

        foreach (app(DeliveryService::class)->cartShops($cart) as $shop) {
            $shopMinimum = $shop['setting']->minimumOrder();
            if ($shopMinimum > 0 && $shop['items_total'] < $shopMinimum) {
                return __(':shop has a minimum order of :amount. Add :more more from this shop, or take its items out.', [
                    'shop' => $shop['name'], 'amount' => Money::format($shopMinimum), 'more' => Money::format($shopMinimum - $shop['items_total']),
                ]);
            }
        }

        return null;
    }

    /**
     * The pause in force now: ['mode' => all|delivery|cod, 'message' => string, 'until' => ?Carbon], or null.
     *
     * @return array{mode: string, message: string, until: ?Carbon}|null
     */
    public static function pause(): ?array
    {
        $mode = (string) Setting::get('ordering_pause_mode', 'off');
        if (! in_array($mode, ['all', 'delivery', 'cod'], true)) {
            return null;
        }
        $from = self::time('ordering_pause_from');
        $until = self::time('ordering_pause_until');
        if (($from && now()->lt($from)) || ($until && now()->gte($until))) {
            return null;
        }

        $message = trim((string) Setting::get('ordering_pause_message', ''));

        return ['mode' => $mode, 'message' => $message !== '' ? $message : self::defaultMessage($mode, $until), 'until' => $until];
    }

    /**
     * The message while all ordering is paused, or null.
     */
    public static function pausedEntirely(): ?string
    {
        $pause = self::pause();

        return $pause && $pause['mode'] === 'all' ? $pause['message'] : null;
    }

    public static function defaultMessage(string $mode, ?Carbon $until): string
    {
        $back = $until ? ' '.__('Back on :date.', ['date' => $until->translatedFormat('j M, H:i')]) : '';

        return match ($mode) {
            'delivery' => __('Delivery is paused for now: you can still order items to collect from the shop.'),
            'cod' => __('Cash on delivery is paused for now: please pay by card or bank transfer.'),
            default => __('Ordering is paused for now.'),
        }.$back;
    }

    /**
     * Why this order can't be placed while ordering is paused, or null.
     *
     * @param  array<string, mixed>  $shippingData  after DeliveryService::prepareOrder()
     */
    public static function pauseProblem(array $shippingData): ?string
    {
        $pause = self::pause();
        if (! $pause) {
            return null;
        }
        $delivered = collect((array) ($shippingData['fulfilment_parts'] ?? []))->contains(fn ($part) => ($part['method'] ?? null) === SellerOrder::METHOD_DELIVER);

        return match (true) {
            $pause['mode'] === 'all' => $pause['message'],
            $pause['mode'] === 'delivery' && $delivered => $pause['message'],
            $pause['mode'] === 'cod' && ($shippingData['payment_method'] ?? null) === ManualPaymentService::COD => $pause['message'],
            default => null,
        };
    }

    protected static function time(string $key): ?Carbon
    {
        $value = trim((string) Setting::get($key, ''));
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value, 'Indian/Maldives')->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
