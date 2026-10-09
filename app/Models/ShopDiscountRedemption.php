<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One order's use of a shop discount code. Uses on cancelled orders no longer count towards the
 * code's limits or the shop's report (see scopeCounted).
 */
class ShopDiscountRedemption extends Model
{
    protected $fillable = ['shop_discount_code_id', 'code', 'seller_id', 'order_id', 'user_id', 'email', 'amount', 'sales'];

    protected $casts = [
        'amount' => 'decimal:2',
        'sales' => 'decimal:2',
    ];

    /** @return BelongsTo<ShopDiscountCode, $this> */
    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(ShopDiscountCode::class, 'shop_discount_code_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Uses that count: the order still stands (not cancelled).
     *
     * @param  Builder<ShopDiscountRedemption>  $query
     * @return Builder<ShopDiscountRedemption>
     */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->whereHas('order', fn (Builder $order) => $order->where('status', '!=', 'cancelled'));
    }
}
