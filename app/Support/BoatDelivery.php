<?php

namespace App\Support;

use App\Models\Island;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\DeliveryService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * "Send with a boat": the customer names a boat leaving the shop's island for theirs, and the shop takes the
 * parcel to the jetty or harbour before it leaves. For iruali it is a local hand-over on the shop's island
 * (the Greater Malé fee, or the shop's own local fee); the customer pays the boat's freight when they collect.
 * Offered when Admin → Delivery has it on and every shop in the cart is on one island, or all in Greater Malé
 * (Malé, Hulhumalé, Villimalé: the Malé jetties).
 */
final class BoatDelivery
{
    /** The address card's value at checkout (address_id=boat). */
    public const CHOICE = 'boat';

    /** The boat must leave at least this long after the order, and after the shops next open. */
    public const MIN_HOURS = 2;

    public const MAX_DAYS = 21;

    /**
     * Where the boat can be met: the Malé jetties for Greater Malé shops, else the shop's island's harbour.
     *
     * @return array<string, string> jetty key => label
     */
    public static function jetties(?Island $from = null): array
    {
        if ($from !== null && ! self::isGreaterMale($from)) {
            return [
                'harbour' => __(':island harbour', ['island' => $from->localized_name]),
                'other' => __('Another place on :island', ['island' => $from->localized_name]),
            ];
        }
        $jetties = [];
        foreach (range(1, 10) as $n) {
            $jetties['male_'.$n] = __('Malé Jetty No. :n', ['n' => $n]);
        }

        return $jetties + [
            'villimale' => __('Villimalé ferry terminal'),
            'hulhumale' => __('Hulhumalé ferry terminal'),
            'other' => __('Another jetty in Greater Malé'),
        ];
    }

    public static function isGreaterMale(Island $island): bool
    {
        return app(DeliveryService::class)->zoneForIsland($island) === DeliveryService::AREA_GREATER_MALE;
    }

    /** The island the jetty is on: in Greater Malé by the jetty, elsewhere the shops' island. */
    public static function jettyIsland(string $jetty, ?Island $from = null): ?Island
    {
        if ($from !== null && ! self::isGreaterMale($from)) {
            return $from;
        }
        $name = match ($jetty) {
            'villimale' => 'Villimalé',
            'hulhumale' => 'Hulhumalé',
            default => 'Malé',
        };

        return Island::where('is_active', true)->get()
            ->first(fn (Island $island) => mb_strtolower((string) $island->getTranslation('name', 'en', false)) === mb_strtolower($name));
    }

    public static function enabled(): bool
    {
        return filter_var(Setting::get('boat_delivery_enabled', 1), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The island the boat leaves from for these cart shops (DeliveryService::cartShops()), or null when it is not
     * offered: switched off, a shop without a home island, or shops on different islands (all in Greater Malé
     * counts as one place). Greater Malé gives Malé.
     *
     * @param  Collection<int, array<string, mixed>>  $shops
     */
    public static function fromIsland(Collection $shops): ?Island
    {
        if (! self::enabled() || $shops->isEmpty()) {
            return null;
        }
        $homes = $shops->map(fn (array $shop) => ($shop['seller'] ?? null) instanceof User ? $shop['seller']->homeIsland : null);
        if ($homes->contains(null)) {
            return null;
        }
        if ($homes->every(fn (Island $island) => self::isGreaterMale($island))) {
            return self::jettyIsland('male_1') ?? $homes->first();
        }

        return $homes->pluck('id')->unique()->count() === 1 ? $homes->first() : null;
    }

    /** @param  Collection<int, array<string, mixed>>  $shops */
    public static function offeredFor(Collection $shops): bool
    {
        return self::fromIsland($shops) !== null;
    }

    /**
     * Why the boat's departure can't work, or null: too soon, too far ahead, or before a shop next opens.
     *
     * @param  Collection<int, array<string, mixed>>  $shops
     */
    public static function departureProblem(CarbonInterface $departs, Collection $shops, ?CarbonInterface $now = null): ?string
    {
        $now = CarbonImmutable::instance($now ?? now());
        if ($departs->lt($now->addHours(self::MIN_HOURS))) {
            return __('The boat must leave at least :hours hours from now, so the shop can get your order to the jetty.', ['hours' => self::MIN_HOURS]);
        }
        if ($departs->gt($now->addDays(self::MAX_DAYS))) {
            return __('Choose a boat leaving within :days days.', ['days' => self::MAX_DAYS]);
        }
        foreach ($shops as $shop) {
            $opens = $shop['setting']->nextWorkingDay($now);
            if ($departs->lt(CarbonImmutable::instance($opens)->addHours(self::MIN_HOURS))) {
                return __(':shop is closed until :when, too late for that boat. Please choose a later boat.', [
                    'shop' => $shop['name'], 'when' => CarbonImmutable::instance($opens)->translatedFormat('l j M'),
                ]);
            }
        }

        return null;
    }

    /**
     * The boat details from the customer's last boat order, to fill the form in again.
     *
     * @return array<string, string>|null
     */
    public static function lastFor(?User $user): ?array
    {
        if (! $user) {
            return null;
        }
        $boat = Order::where('user_id', $user->id)->whereNotNull('boat')->latest('id')->value('boat');
        $boat = is_string($boat) ? json_decode($boat, true) : $boat;

        return is_array($boat) && ! empty($boat['name']) ? $boat : null;
    }

    /** Where the boat is met, as stored on the order (older orders: from the Malé jetty list). */
    public static function jettyLabel(array $boat): string
    {
        if (($boat['jetty'] ?? '') === 'other' && filled($boat['jetty_other'] ?? null)) {
            return (string) $boat['jetty_other'];
        }

        return (string) ($boat['jetty_label'] ?? (self::jetties()[$boat['jetty'] ?? ''] ?? ''));
    }

    /** "Express 3 · Malé Jetty No. 5 · leaves Sat 2 Nov 15:00" */
    public static function summary(array $boat): string
    {
        $jetty = self::jettyLabel($boat);
        $departs = ! empty($boat['departs_at']) ? CarbonImmutable::parse($boat['departs_at'])->translatedFormat('D j M H:i') : null;

        return collect([$boat['name'] ?? null, $jetty ?: null, $departs ? __('leaves :when', ['when' => $departs]) : null])->filter()->join(' · ');
    }
}
