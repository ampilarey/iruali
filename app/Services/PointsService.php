<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PointsTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PointsExpiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Every change to a customer's loyalty points goes through record(), which moves the balance and
 * writes the ledger row together. Expiry is first-in-first-out over the ledger: the oldest points
 * still unspent are the ones that expire.
 */
class PointsService
{
    /**
     * Move points and write the ledger row. A negative $points takes points away.
     */
    public function record(User $user, int $points, string $type, ?Order $order = null, ?string $note = null): ?PointsTransaction
    {
        if ($points === 0) {
            return null;
        }
        if (! in_array($type, PointsTransaction::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown points type {$type}");
        }

        return DB::transaction(function () use ($user, $points, $type, $order, $note) {
            User::whereKey($user->id)->increment('loyalty_points', $points);
            $user->loyalty_points = (int) $user->loyalty_points + $points;
            $user->syncOriginalAttribute('loyalty_points');

            return PointsTransaction::create([
                'user_id' => $user->id,
                'points' => $points,
                'type' => $type,
                'order_id' => $order?->id,
                'note' => $note,
                'created_at' => now(),
            ]);
        });
    }

    public function expiryMonths(): int
    {
        return max(0, (int) Setting::get('points_expire_months'));
    }

    /**
     * The customer's unspent points grouped by the day they were earned, oldest first (FIFO):
     * [['earned_at' => Carbon, 'points' => int], ...]. Spending (and expiry) always eats the oldest lot.
     *
     * @return list<array{earned_at: \Carbon\Carbon, points: int}>
     */
    public function unspentLots(User $user): array
    {
        $lots = [];
        foreach (PointsTransaction::where('user_id', $user->id)->orderBy('created_at')->orderBy('id')->get() as $row) {
            if ($row->points > 0) {
                $lots[] = ['earned_at' => $row->created_at, 'points' => $row->points];

                continue;
            }
            $take = -$row->points;
            foreach ($lots as $i => $lot) {
                if ($take <= 0) {
                    break;
                }
                $used = min($lot['points'], $take);
                $lots[$i]['points'] -= $used;
                $take -= $used;
            }
            $lots = array_values(array_filter($lots, fn ($lot) => $lot['points'] > 0));
        }

        return $lots;
    }

    /**
     * Points that were earned before $before and are still unspent.
     */
    public function pointsExpiringBefore(User $user, Carbon $before): int
    {
        return (int) collect($this->unspentLots($user))->filter(fn ($lot) => $lot['earned_at']->lt($before))->sum('points');
    }

    /**
     * Expire points older than the configured number of months, for every customer with a balance.
     * Returns the number of customers whose points expired.
     */
    public function expireOld(): int
    {
        $months = $this->expiryMonths();
        if ($months <= 0) {
            return 0;
        }
        $cutoff = now()->subMonths($months);
        $count = 0;

        User::where('loyalty_points', '>', 0)
            ->whereHas('pointsTransactions', fn ($q) => $q->where('points', '>', 0)->where('created_at', '<', $cutoff))
            ->orderBy('id')
            ->each(function (User $user) use ($cutoff, &$count) {
                $expiring = min($this->pointsExpiringBefore($user, $cutoff), (int) $user->loyalty_points);
                if ($expiring > 0) {
                    $this->record($user, -$expiring, 'expired', null, __('Points older than :months months expired', ['months' => $this->expiryMonths()]));
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Warn customers whose points will expire within the next month. Each customer is warned at most
     * once a month.
     */
    public function remindExpiring(): int
    {
        $months = $this->expiryMonths();
        if ($months <= 0) {
            return 0;
        }
        $expiresOn = now()->addMonth();
        $cutoff = $expiresOn->copy()->subMonths($months); // points earned before this will be gone in a month
        $sent = 0;

        User::where('loyalty_points', '>', 0)
            ->whereNotNull('email')
            ->where(fn ($q) => $q->whereNull('points_expiry_reminded_at')->orWhere('points_expiry_reminded_at', '<', now()->subDays(27)))
            ->whereHas('pointsTransactions', fn ($q) => $q->where('points', '>', 0)->where('created_at', '<', $cutoff))
            ->orderBy('id')
            ->each(function (User $user) use ($cutoff, $expiresOn, &$sent) {
                $expiring = min($this->pointsExpiringBefore($user, $cutoff), (int) $user->loyalty_points);
                if ($expiring <= 0) {
                    return;
                }
                try {
                    $user->notify(new PointsExpiring($expiring, $expiresOn));
                    $user->forceFill(['points_expiry_reminded_at' => now()])->save();
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                }
            });

        return $sent;
    }

    /**
     * Mask a name for the referrals table: "Aishath Mohamed" → "Ai***** M."
     */
    public static function maskName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '***';
        }
        $parts = preg_split('/\s+/u', $name);
        $first = array_shift($parts);
        $masked = mb_substr($first, 0, 2).str_repeat('*', max(1, mb_strlen($first) - 2));
        $last = $parts ? ' '.mb_substr(end($parts), 0, 1).'.' : '';

        return $masked.$last;
    }
}
