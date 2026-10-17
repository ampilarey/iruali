<?php

namespace App\Support;

use App\Mail\StaffAccountLocked;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Staff accounts are locked for a while after repeated wrong passwords or two-step codes, and the
 * owner is told (email to the alerts inbox, and Admin → Team alerts). Customers are covered by the
 * sign-in rate limit; a staff account opens the admin, so a guessed password matters more.
 * An admin unlocks it from Admin → Users, or `php artisan staff:unlock email` on the server.
 */
class StaffLockout
{
    public const ATTEMPTS = 5;

    public const WINDOW_MINUTES = 15;

    public const LOCK_MINUTES = 30;

    public static function lockedUntil(User $user): ?Carbon
    {
        $until = Cache::get(self::lockKey($user));

        return $until && now()->timestamp < $until ? Carbon::createFromTimestamp($until) : null;
    }

    /**
     * A wrong password or code for this account. Locks a staff account on the last allowed try.
     */
    public static function failed(User $user, ?string $ip): void
    {
        if (! $user->isStaff()) {
            return;
        }

        $key = 'staff-fails:'.$user->id;
        Cache::add($key, 0, now()->addMinutes(self::WINDOW_MINUTES));
        if (Cache::increment($key) < self::ATTEMPTS) {
            return;
        }

        Cache::forget($key);
        $until = now()->addMinutes(self::LOCK_MINUTES);
        Cache::put(self::lockKey($user), $until->timestamp, $until);
        Audit::record('staff.locked', $user, ['ip' => $ip, 'until' => $until->toDateTimeString()]);

        try {
            if ($to = ErrorTracker::alertsTo()) {
                Mail::to($to)->queue(new StaffAccountLocked($user->name, $user->email, (string) $ip, $until));
            }
            TeamAlerts::send('staff_locked', 'iruali: staff account '.$user->email.' locked after '.self::ATTEMPTS.' wrong sign-in tries (IP '.$ip.').', route('admin.users'));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function succeeded(User $user): void
    {
        Cache::forget('staff-fails:'.$user->id);
    }

    public static function unlock(User $user): void
    {
        Cache::forget(self::lockKey($user));
        Cache::forget('staff-fails:'.$user->id);
    }

    protected static function lockKey(User $user): string
    {
        return 'staff-lock:'.$user->id;
    }
}
