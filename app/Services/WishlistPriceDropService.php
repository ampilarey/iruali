<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wishlist;
use App\Notifications\WishlistPriceDrop;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The daily price-drop alert (php artisan wishlist:price-drops, 10:00 Maldives time): one email
 * per customer, plus a push on devices that allowed it, listing wishlisted products whose price
 * now is at least Wishlist::DROP_MIN_PERCENT and Wishlist::DROP_MIN_AMOUNT under the last price
 * the customer was told about (or the price when they saved it). Each listed item's reference
 * then moves to today's price, so the same drop is never reported twice. Products that are
 * hidden, sold out or in the bin are skipped, as are customers who switched the alert or
 * marketing emails off, inactive or banned accounts and the smoke-test customer.
 */
class WishlistPriceDropService
{
    /** Items listed in one alert, biggest drops first; the rest wait for the next day's alert. */
    public const MAX_ITEMS = 12;

    /**
     * Alert every customer with a drop. Returns how many alerts went out.
     */
    public function sendAll(): int
    {
        $sent = 0;

        Wishlist::query()->distinct()->orderBy('user_id')->pluck('user_id')
            ->chunk(100)
            ->each(function (Collection $ids) use (&$sent) {
                foreach (User::query()->whereIn('id', $ids)->orderBy('id')->get() as $user) {
                    $sent += $this->sendTo($user) ? 1 : 0;
                }
            });

        return $sent;
    }

    /**
     * One customer's alert. Returns whether one went out.
     */
    public function sendTo(User $user): bool
    {
        // A product in the bin is left out (the relation skips it), as on the wishlist page
        $items = Wishlist::query()->where('user_id', $user->id)->whereHas('product')
            ->with(['product.variants', 'variant'])->orderBy('id')->get();

        // Saved before prices were tracked: today's price is where drops are measured from
        foreach ($items->whereNull('saved_price') as $item) {
            $price = $item->currentPrice();
            if ($price !== null) {
                $item->forceFill(['saved_price' => $price])->save();
            }
        }

        if (! $user->wantsWishlistPriceDrops()) {
            return false;
        }

        $drops = $this->drops($items);
        if ($drops === []) {
            return false;
        }

        $listed = array_slice($drops, 0, self::MAX_ITEMS);

        try {
            $user->notify(new WishlistPriceDrop($listed));
        } catch (Throwable $e) {
            report($e);

            return false; // nothing moved, so tomorrow's run tries again
        }

        foreach ($listed as $drop) {
            Wishlist::query()->whereKey($drop['wishlist'])->update(['notified_price' => $drop['now'], 'price_drop_notified_at' => now()]);
        }

        return true;
    }

    /**
     * The items worth an alert, biggest drop first.
     *
     * @param  Collection<int, Wishlist>  $items
     * @return list<array{wishlist: int, product: int, variant: int|null, was: float, now: float}>
     */
    public function drops(Collection $items): array
    {
        $drops = [];
        foreach ($items as $item) {
            if (! $item->isBuyable()) {
                continue;
            }

            $was = $item->referencePrice();
            $now = $item->currentPrice();
            if (Wishlist::isPriceDrop($was, $now)) {
                $drops[] = ['wishlist' => (int) $item->id, 'product' => (int) $item->product_id, 'variant' => $item->product_variant_id ? (int) $item->product_variant_id : null, 'was' => (float) $was, 'now' => (float) $now];
            }
        }

        usort($drops, fn (array $a, array $b) => [$b['was'] - $b['now'], $a['wishlist']] <=> [$a['was'] - $a['now'], $b['wishlist']]);

        return $drops;
    }
}
