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
 * "Send with a boat": the customer names a boat leaving Greater Malé for their island, and the shop takes
 * the parcel to that jetty before it leaves. For iruali it is a Greater Malé delivery (the Greater Malé fee,
 * the shops' Greater Malé reach); the customer pays the boat's freight when they collect. Offered when
 * Admin → Delivery has it on and every shop in the cart is in Greater Malé (it can get to the jetties).
 */
final class BoatDelivery
{
    /** The address card's value at checkout (address_id=boat). */
    public const CHOICE = 'boat';

    /** The boat must leave at least this long after the order, and after the shops next open. */
    public const MIN_HOURS = 2;

    public const MAX_DAYS = 21;

    /** @return array<string, string> jetty key => label */
    public static function jetties(): array
    {
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

    /** The island the jetty is on, by its English name. */
    public static function jettyIslandName(string $jetty): string
    {
        return match ($jetty) {
            'villimale' => 'Villimalé',
            'hulhumale' => 'Hulhumalé',
            default => 'Malé',
        };
    }

    public static function jettyIsland(string $jetty): ?Island
    {
        $name = self::jettyIslandName($jetty);

        return Island::where('is_active', true)->get()
            ->first(fn (Island $island) => mb_strtolower((string) $island->getTranslation('name', 'en', false)) === mb_strtolower($name));
    }

    public static function enabled(): bool
    {
        return filter_var(Setting::get('boat_delivery_enabled', 1), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Offered for these cart shops (DeliveryService::cartShops()): switched on, and every shop is in Greater Malé.
     *
     * @param  Collection<int, array<string, mixed>>  $shops
     */
    public static function offeredFor(Collection $shops): bool
    {
        if (! self::enabled() || $shops->isEmpty()) {
            return false;
        }
        $delivery = app(DeliveryService::class);

        return $shops->every(function (array $shop) use ($delivery) {
            $seller = $shop['seller'] ?? null;
            $home = $seller instanceof User ? $seller->homeIsland : null;

            return $home !== null && $delivery->zoneForIsland($home) === DeliveryService::AREA_GREATER_MALE;
        });
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

    /** "Express 3 · Malé Jetty No. 5 · leaves Sat 2 Nov 15:00" */
    public static function summary(array $boat): string
    {
        $jetty = ($boat['jetty'] ?? '') === 'other' ? (string) ($boat['jetty_other'] ?? '') : (self::jetties()[$boat['jetty'] ?? ''] ?? '');
        $departs = ! empty($boat['departs_at']) ? CarbonImmutable::parse($boat['departs_at'])->translatedFormat('D j M H:i') : null;

        return collect([$boat['name'] ?? null, $jetty ?: null, $departs ? __('leaves :when', ['when' => $departs]) : null])->filter()->join(' · ');
    }
}
