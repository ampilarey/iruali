<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A shop's delivery settings (Seller Centre → Settings → Delivery & pickup): how many days it
 * usually takes to send an order, whether customers may collect from the shop instead, and its
 * own delivery fees (own_fees: to its home island, Greater Malé and other islands, free over an
 * amount). A shop that never saved the page gets the defaults (ships within 1 day, no pickup,
 * iruali's delivery fees).
 */
class SellerDeliverySetting extends Model
{
    public const DEFAULT_SHIPS_WITHIN_DAYS = 1;

    protected $fillable = ['seller_id', 'ships_within_days', 'pickup_enabled', 'pickup_address', 'pickup_island_id', 'pickup_hours',
        'own_fees', 'fee_local', 'fee_greater_male', 'fee_islands', 'free_over', 'min_order_amount', 'opening_hours'];

    protected $attributes = [
        'ships_within_days' => self::DEFAULT_SHIPS_WITHIN_DAYS,
        'pickup_enabled' => false,
        'own_fees' => false,
    ];

    protected $casts = [
        'ships_within_days' => 'integer',
        'pickup_enabled' => 'boolean',
        'own_fees' => 'boolean',
        'fee_local' => 'decimal:2',
        'fee_greater_male' => 'decimal:2',
        'fee_islands' => 'decimal:2',
        'free_over' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'opening_hours' => 'array',
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

    /** ISO weekday => English day name, for the hours form */
    public const DAYS = [6 => 'Saturday', 7 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];

    /** At most this many times a day (e.g. 09:00–12:00 and 14:00–22:00 around a break) */
    public const MAX_RANGES = 3;

    public function hasOpeningHours(): bool
    {
        return collect(array_keys(self::DAYS))->contains(fn (int $day) => $this->rangesOn($day) !== []);
    }

    /**
     * A day's opening times, in order: [[open, close], ...]; empty when closed. "24:00" closes at midnight
     * (an all-day [00:00, 24:00]); a close before the open runs past midnight (18:00–02:00). Hours saved in
     * the first, one-range form ([open, close]) read the same.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function rangesOn(int $weekday): array
    {
        $day = ((array) $this->opening_hours)[(string) $weekday] ?? null;
        if (! is_array($day) || $day === []) {
            return [];
        }
        $ranges = is_array(reset($day)) ? $day : [$day];

        return collect($ranges)
            ->filter(fn ($r) => is_array($r) && count($r) === 2 && filled($r[0]) && filled($r[1]))
            ->map(fn ($r) => [(string) $r[0], (string) $r[1] === '00:00' ? '24:00' : (string) $r[1]])
            ->sortBy(fn (array $r) => $r[0])->values()->all();
    }

    /**
     * The first opening and last closing time of a day, or null when closed.
     *
     * @return array{0: string, 1: string}|null
     */
    public function hoursOn(int $weekday): ?array
    {
        $ranges = $this->rangesOn($weekday);

        return $ranges === [] ? null : [$ranges[0][0], end($ranges)[1]];
    }

    /** Open all day (00:00–24:00). */
    public function isOpenAllDay(int $weekday): bool
    {
        return $this->rangesOn($weekday) === [['00:00', '24:00']];
    }

    /** A day's hours for people: "09:00–12:00, 14:00–22:00", "24 hours", or null when closed. */
    public function dayText(int $weekday): ?string
    {
        if ($this->isOpenAllDay($weekday)) {
            return __('24 hours');
        }
        $ranges = $this->rangesOn($weekday);

        return $ranges === [] ? null : collect($ranges)->map(fn ($r) => $r[0].'–'.($r[1] === '24:00' ? '00:00' : $r[1]))->join(', ');
    }

    /** Whether the shop is open at this moment (also late-night hours carried over from the day before). */
    public function isOpenAt(CarbonInterface $at): bool
    {
        $time = $at->format('H:i');
        foreach ($this->rangesOn($at->dayOfWeekIso) as [$open, $close]) {
            if ($close > $open ? ($time >= $open && $time < $close) : $time >= $open) {
                return true;
            }
        }
        foreach ($this->rangesOn($at->subDay()->dayOfWeekIso) as [$open, $close]) {
            if ($close < $open && $time < $close) {
                return true;
            }
        }

        return false;
    }

    /** Whether some opening is still to come today, or the shop is open now. */
    protected function opensLaterToday(CarbonInterface $at): bool
    {
        $time = $at->format('H:i');

        return $this->isOpenAt($at) || collect($this->rangesOn($at->dayOfWeekIso))->contains(fn ($r) => $r[0] > $time);
    }

    /**
     * When the shop next works on orders: now while it is open (or before an opening later today), else
     * the start of the next day it opens. Without opening hours: now.
     */
    public function nextWorkingDay(?CarbonInterface $at = null): CarbonImmutable
    {
        $at = CarbonImmutable::instance($at ?? now());
        if (! $this->hasOpeningHours() || $this->opensLaterToday($at)) {
            return $at;
        }
        for ($i = 1; $i < 8; $i++) {
            $day = $at->addDays($i);
            if ($this->rangesOn($day->dayOfWeekIso) !== []) {
                return $day->startOfDay();
            }
        }

        return $at;
    }

    /**
     * "Open today 09:00–12:00, 14:00–22:00", "Closed now, opens Sunday 09:00", or null without opening hours.
     */
    public function hoursText(?CarbonInterface $at = null): ?string
    {
        if (! $this->hasOpeningHours()) {
            return null;
        }
        $at = CarbonImmutable::instance($at ?? now());
        if ($this->opensLaterToday($at) && ($today = $this->dayText($at->dayOfWeekIso)) !== null) {
            return $this->isOpenAllDay($at->dayOfWeekIso) ? __('Open 24 hours today') : __('Open today :hours', ['hours' => $today]);
        }
        if ($this->isOpenAt($at)) {
            return __('Open now'); // late-night hours from yesterday
        }
        // Closed for the rest of today: the next opening (today's later slot is handled above)
        for ($i = 1; $i < 8; $i++) {
            $day = $at->addDays($i);
            if ($ranges = $this->rangesOn($day->dayOfWeekIso)) {
                return __('Closed now, opens :day :time', ['day' => $day->translatedFormat('l'), 'time' => $ranges[0][0]]);
            }
        }

        return null;
    }

    public function minimumOrder(): float
    {
        return max(0, (float) $this->min_order_amount);
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

    /**
     * Admin → Delivery lets shops set their own delivery fees.
     */
    public static function ownFeesAllowed(): bool
    {
        return filter_var(Setting::get('shop_own_fees_enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The most a shop may charge for one delivery (Admin → Delivery).
     */
    public static function maxFee(): float
    {
        return max(0, (float) Setting::get('shop_delivery_fee_max'));
    }

    /**
     * The shop delivers its own parts at its own fees (and keeps them), while iruali allows it.
     */
    public function hasOwnFees(): bool
    {
        return (bool) $this->own_fees && $this->fee_greater_male !== null && $this->fee_islands !== null && self::ownFeesAllowed();
    }

    /**
     * The shop's own fee for a delivery to the area (DeliveryService::AREA_GREATER_MALE or any
     * other), to its home island when $toHomeIsland (the local fee, when set), for this many
     * MVR of its items: 0 from its free-delivery amount, never above iruali's limit. Null when the
     * shop uses iruali's fees.
     */
    public function ownFee(string $area, bool $toHomeIsland, float $itemsTotal): ?float
    {
        if (! $this->hasOwnFees()) {
            return null;
        }
        if ($this->free_over !== null && (float) $this->free_over > 0 && $itemsTotal >= (float) $this->free_over) {
            return 0.0;
        }
        $fee = match (true) {
            $toHomeIsland && $this->fee_local !== null => (float) $this->fee_local,
            $area === \App\Services\DeliveryService::AREA_GREATER_MALE => (float) $this->fee_greater_male,
            default => (float) $this->fee_islands,
        };

        return round(min($fee, self::maxFee()), 2);
    }
}
