<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A shop's delivery settings (Seller Centre → Settings → Delivery & pickup): how many days it
 * usually takes to send an order, and whether customers may collect from the shop instead.
 * A shop that never saved the page gets the defaults (ships within 1 day, no pickup).
 */
class SellerDeliverySetting extends Model
{
    public const DEFAULT_SHIPS_WITHIN_DAYS = 1;

    protected $fillable = ['seller_id', 'ships_within_days', 'pickup_enabled', 'pickup_address', 'pickup_island_id', 'pickup_hours'];

    protected $attributes = [
        'ships_within_days' => self::DEFAULT_SHIPS_WITHIN_DAYS,
        'pickup_enabled' => false,
    ];

    protected $casts = [
        'ships_within_days' => 'integer',
        'pickup_enabled' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<Island, $this> */
    public function pickupIsland(): BelongsTo
    {
        return $this->belongsTo(Island::class, 'pickup_island_id');
    }

    /**
     * The shop's settings, or unsaved defaults when it has none (or the part has no shop).
     */
    public static function for(?int $sellerId): self
    {
        $setting = $sellerId ? static::with('pickupIsland')->where('seller_id', $sellerId)->first() : null;

        return $setting ?? new self(['seller_id' => $sellerId]);
    }

    /**
     * Settings for several shops at once, keyed by seller id (defaults for shops without a row).
     *
     * @param  iterable<int|null>  $sellerIds
     * @return Collection<int, self>
     */
    public static function forSellers(iterable $sellerIds): Collection
    {
        $ids = collect($sellerIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $saved = $ids->isEmpty() ? collect() : static::with('pickupIsland')->whereIn('seller_id', $ids)->get()->keyBy('seller_id');

        return $ids->mapWithKeys(fn (int $id) => [$id => $saved[$id] ?? new self(['seller_id' => $id])]);
    }

    public function shipsWithinDays(): int
    {
        return max(0, (int) ($this->ships_within_days ?? self::DEFAULT_SHIPS_WITHIN_DAYS));
    }

    /**
     * Customers can choose to collect: pickup is on and the shop said where.
     */
    public function offersPickup(): bool
    {
        return (bool) $this->pickup_enabled && filled($this->pickup_address) && $this->pickup_island_id !== null;
    }

    public function pickupIslandName(): string
    {
        $island = $this->pickupIsland;
        if (! $island) {
            return '';
        }

        return trim((string) $island->localized_name.($island->atoll ? ', '.$island->atoll : ''));
    }

    /**
     * The island in English, as stored on an order part (shown again in any language).
     */
    public function pickupIslandForOrder(): string
    {
        $island = $this->pickupIsland;
        if (! $island) {
            return '';
        }
        $name = $island->getTranslation('name', 'en', false) ?: $island->getTranslation('name', config('app.fallback_locale'), false);

        return trim($name.($island->atoll ? ', '.$island->atoll : ''));
    }
}
