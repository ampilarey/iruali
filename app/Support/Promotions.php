<?php

namespace App\Support;

use App\Models\Cart;
use App\Models\Voucher;
use App\Services\DiscountService;
use Illuminate\Support\Collection;

/**
 * Automatic offers (Admin → Vouchers, "Apply automatically"): no code to type. When the shopper
 * has not entered a code, the best money-off offer the cart qualifies for is applied in the
 * voucher's place, and an automatic free-delivery offer makes delivery free over its minimum.
 */
class Promotions
{
    /**
     * Automatic offers running now (valid dates, not used up). One small query, not cached: a queue
     * worker lives across many jobs, and an offer switched off must stop at once.
     *
     * @return Collection<int, Voucher>
     */
    public static function active(): Collection
    {
        return Voucher::where('is_active', true)->where('is_automatic', true)->whereNull('user_id')
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->get()->filter(fn (Voucher $v) => ! $v->max_uses || $v->used_count < $v->max_uses)->values();
    }

    /**
     * The money-off offer giving the most on this cart, or null.
     */
    public static function bestFor(Cart $cart, float $base): ?Voucher
    {
        $discounts = app(DiscountService::class);

        return static::active()->filter(fn (Voucher $v) => (float) $v->amount > 0 && $discounts->validateVoucher($v->code, $cart, $base)['valid'])
            ->sortByDesc(fn (Voucher $v) => $discounts->calculateVoucherAmount($cart, $v, $base))->first();
    }

    /**
     * Delivery is free under a running automatic free-delivery offer this order reaches.
     */
    public static function freeDelivery(float $goodsTotal): bool
    {
        return static::active()->contains(fn (Voucher $v) => $v->free_delivery && $goodsTotal >= (float) ($v->min_order ?? 0));
    }
}
