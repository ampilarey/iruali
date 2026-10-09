<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shop's multi-buy offer: up to three quantity tiers ("2+ = 5% off, 3+ = 10% off"), funded by
 * the shop. An offer without a name belongs to one product; a named offer is a mix-and-match
 * group, and every product in it counts towards the tiers together.
 */
class MultiBuyOffer extends Model
{
    public const MAX_TIERS = 3;

    protected $table = 'multibuy_offers';

    /** seller_id is set by MultiBuyService from the product's shop, never from input. */
    protected $fillable = ['name', 'tiers'];

    protected $casts = [
        'tiers' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'multibuy_offer_id');
    }

    /**
     * A mix-and-match group shared by several products (rather than one product's own offer).
     */
    public function isShared(): bool
    {
        return filled($this->name);
    }

    /**
     * The tiers, smallest quantity first, with clean types.
     *
     * @return list<array{min_qty: int, percent: float}>
     */
    public function tierList(): array
    {
        $tiers = [];
        foreach ((array) $this->tiers as $tier) {
            $qty = (int) ($tier['min_qty'] ?? 0);
            $percent = round((float) ($tier['percent'] ?? 0), 2);
            if ($qty >= 2 && $percent > 0 && $percent <= 90) {
                $tiers[] = ['min_qty' => $qty, 'percent' => $percent];
            }
        }
        usort($tiers, fn ($a, $b) => $a['min_qty'] <=> $b['min_qty']);

        return $tiers;
    }

    /**
     * The best tier this many units reach, or null below the first one.
     *
     * @return array{min_qty: int, percent: float}|null
     */
    public function tierFor(int $quantity): ?array
    {
        $reached = null;
        foreach ($this->tierList() as $tier) {
            if ($quantity >= $tier['min_qty']) {
                $reached = $tier;
            }
        }

        return $reached;
    }

    /**
     * The next tier up from this many units, or null when the top one is reached.
     *
     * @return array{min_qty: int, percent: float}|null
     */
    public function nextTier(int $quantity): ?array
    {
        foreach ($this->tierList() as $tier) {
            if ($quantity < $tier['min_qty']) {
                return $tier;
            }
        }

        return null;
    }
}
