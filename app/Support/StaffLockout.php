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
 *
 * Wrong passwords lock the account for the address (IP) they came from only, so a stranger who
 * knows a staff email can't keep that person out by failing on purpose; the right password from
 * anywhere else still works. Past ACCOUNT_ALERT_AT wrong passwords in an hour from all addresses
 * together, the owner is told once (no lock). Wrong two-step codes lock the whole account: only
 * someone who already got the password right reaches that step.
 * An admin unlocks it from Admin → Users, or `php artisan staff:unlock email` on the server.
 */
class StaffLockout
{
    public const ATTEMPTS = 5;

    public const WINDOW_MINUTES = 15;

    public const LOCK_MINUTES = 30;

    /** Wrong passwords in an hour, from every address together, before the owner is told. */
    public const ACCOUNT_ALERT_AT = 50;

    /**
     * When the account is locked until, for this address (IP), or for any address when none is given.
     */
    public static function lockedUntil(User $user, ?string $ip = null): ?Carbon
    {
        $keys = [self::lockKey($user)];
        $keys = array_merge($keys, $ip !== null ? [self::lockKey($user, $ip)] : array_map(fn ($hash) => self::lockKey($user, null, $hash), self::lockedAddresses($user)));
        $until = collect($keys)->map(fn ($key) => (int) Cache::get($key))->max();

        return $until && now()->timestamp < $until ? Carbon::createFromTimestamp($until) : null;
    }

    /**
     * A wrong password (or, with $twoStep, a wrong two-step code) for this account. Locks a staff
     * account on the last allowed try: for that address, or for every address after a wrong code.
     */
    public static function failed(User $user, ?string $ip, bool $twoStep = false): void
    {
        if (! $user->isStaff()) {
            return;
        }

        if (! $twoStep) {
            self::countTowardsAlert($user, $ip);
        }
        $key = $twoStep ? 'staff-fails:'.$user->id : 'staff-fails:'.$user->id.':'.self::hash($ip);
        Cache::add($key, 0, now()->addMinutes(self::WINDOW_MINUTES));
        if (Cache::increment($key) < self::ATTEMPTS) {
            return;
        }

        Cache::forget($key);
        $until = now()->addMinutes(self::LOCK_MINUTES);
        if ($twoStep) {
            Cache::put(self::lockKey($user), $until->timestamp, $until);
        } else {
            Cache::put(self::lockKey($user, $ip), $until->timestamp, $until);
            Cache::put('staff-locked-ips:'.$user->id, array_values(array_unique([...self::lockedAddresses($user), self::hash($ip)])), $until);
        }
        Audit::record('staff.locked', $user, ['ip' => $ip, 'until' => $until->toDateTimeString(), 'scope' => $twoStep ? 'account' : 'address']);

        try {
            if ($to = ErrorTracker::alertsTo()) {
                Mail::to($to)->queue(new StaffAccountLocked($user->name, $user->email, (string) $ip, $until));
            }
            TeamAlerts::send('staff_locked', 'iruali: staff account '.$user->email.' locked after '.self::ATTEMPTS.' wrong sign-in tries (IP '.$ip.').', route('admin.users'));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function succeeded(User $user, ?string $ip = null): void
    {
        Cache::forget('staff-fails:'.$user->id);
        if ($ip !== null) {
            Cache::forget('staff-fails:'.$user->id.':'.self::hash($ip));
        }
    }

    public static function unlock(User $user): void
    {
        foreach (self::lockedAddresses($user) as $hash) {
            Cache::forget(self::lockKey($user, null, $hash));
            Cache::forget('staff-fails:'.$user->id.':'.$hash);
        }
        Cache::forget('staff-locked-ips:'.$user->id);
        Cache::forget(self::lockKey($user));
        Cache::forget('staff-fails:'.$user->id);
    }

    /**
     * Many wrong passwords from all addresses together: tell the owner once an hour, without locking.
     */
    protected static function countTowardsAlert(User $user, ?string $ip): void
    {
        $key = 'staff-fails-all:'.$user->id;
        Cache::add($key, 0, now()->addHour());
        if (Cache::increment($key) < self::ACCOUNT_ALERT_AT || ! Cache::add('staff-fails-alerted:'.$user->id, true, now()->addHour())) {
            return;
        }
        Audit::record('staff.many_wrong_passwords', $user, ['ip' => $ip, 'count' => self::ACCOUNT_ALERT_AT]);
        try {
            TeamAlerts::send('staff_locked', 'iruali: '.self::ACCOUNT_ALERT_AT.' wrong passwords in an hour for staff account '.$user->email.' (latest from IP '.$ip.'). Each address is locked after '.self::ATTEMPTS.'.', route('admin.users'));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return list<string> the hashed addresses currently locked out of this account
     */
    protected static function lockedAddresses(User $user): array
    {
        $hashes = Cache::get('staff-locked-ips:'.$user->id);

        return is_array($hashes) ? array_values(array_filter($hashes, 'is_string')) : [];
    }

    protected static function hash(?string $ip): string
    {
        return substr(hash('sha256', (string) $ip), 0, 16);
    }

    protected static function lockKey(User $user, ?string $ip = null, ?string $hash = null): string
    {
        $hash ??= $ip !== null ? self::hash($ip) : null;

        return 'staff-lock:'.$user->id.($hash !== null ? ':'.$hash : '');
    }
}
