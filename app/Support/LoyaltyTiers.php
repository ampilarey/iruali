<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;

/**
 * Loyalty tiers by what a customer spent on paid orders in the last 12 months: Reef (everyone),
 * Lagoon and Atoll, each earning more points per MVR (Admin → Settings → Loyalty & referrals).
 * A customer who moves up is told once (OrderService::awardRewards); the nightly
 * rewards:refresh-tiers moves people down quietly as old orders pass the 12 months.
 */
class LoyaltyTiers
{
    public const ORDER = ['reef', 'lagoon', 'atoll'];

    public static function enabled(): bool
    {
        return filter_var(Setting::get('loyalty_tiers_enabled', 1), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, array{name: string, min: float, multiplier: float}>
     */
    public static function all(): array
    {
        return [
            'reef' => ['name' => __('Reef'), 'min' => 0.0, 'multiplier' => 1.0],
            'lagoon' => ['name' => __('Lagoon'), 'min' => (float) Setting::get('loyalty_tier_lagoon_min', 5000), 'multiplier' => (float) Setting::get('loyalty_tier_lagoon_multiplier', 1.25)],
            'atoll' => ['name' => __('Atoll'), 'min' => (float) Setting::get('loyalty_tier_atoll_min', 15000), 'multiplier' => (float) Setting::get('loyalty_tier_atoll_multiplier', 1.5)],
        ];
    }

    /**
     * Paid, not cancelled orders in the last 12 months, less refunds.
     */
    public static function spend(User $user): float
    {
        $orders = Order::where('user_id', $user->id)->where('payment_status', 'paid')->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subYear());

        return max(0, (float) (clone $orders)->sum('total_amount') - (float) (clone $orders)->where('refund_status', 'refunded')->sum('refund_amount'));
    }

    public static function tierFor(float $spend): string
    {
        $tier = 'reef';
        foreach (self::all() as $key => $definition) {
            if ($spend >= $definition['min']) {
                $tier = $key;
            }
        }

        return $tier;
    }

    public static function current(User $user): string
    {
        return self::enabled() ? self::tierFor(self::spend($user)) : 'reef';
    }

    public static function multiplier(?User $user): float
    {
        return $user && self::enabled() ? self::all()[self::current($user)]['multiplier'] : 1.0;
    }

    /**
     * The next tier and how much more spending reaches it, or null at the top.
     *
     * @return array{key: string, name: string, needed: float, progress: float}|null
     */
    public static function next(User $user): ?array
    {
        $spend = self::spend($user);
        $current = self::tierFor($spend);
        $index = array_search($current, self::ORDER, true);
        $nextKey = self::ORDER[$index + 1] ?? null;
        if (! $nextKey) {
            return null;
        }
        $tiers = self::all();
        $from = $tiers[$current]['min'];
        $to = $tiers[$nextKey]['min'];

        return ['key' => $nextKey, 'name' => $tiers[$nextKey]['name'], 'needed' => max(0, $to - $spend), 'progress' => $to > $from ? min(1, max(0, ($spend - $from) / ($to - $from))) : 1];
    }

    /**
     * Store the customer's tier; true when it went up (so they can be told).
     */
    public static function refresh(User $user): bool
    {
        $now = self::current($user);
        $before = $user->loyalty_tier ?: 'reef';
        if ($now === $before && $user->loyalty_tier !== null) {
            return false;
        }
        $user->forceFill(['loyalty_tier' => $now])->save();

        return array_search($now, self::ORDER, true) > array_search($before, self::ORDER, true);
    }
}
