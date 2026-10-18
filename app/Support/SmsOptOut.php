<?php

namespace App\Support;

use App\Models\User;

/**
 * A short link at the end of a campaign SMS that stops offers by SMS for that customer
 * (/stop/{code}: the user id and a short signature, so nobody can stop someone else's).
 */
class SmsOptOut
{
    public static function code(User $user): string
    {
        return base_convert((string) $user->id, 10, 36).'-'.substr(hash_hmac('sha256', 'sms-stop:'.$user->id, (string) config('app.key')), 0, 8);
    }

    public static function url(User $user): string
    {
        return route('sms.stop', ['code' => self::code($user)]);
    }

    public static function user(string $code): ?User
    {
        if (! preg_match('/^([0-9a-z]+)-([0-9a-f]{8})$/', $code, $m)) {
            return null;
        }
        $user = User::find((int) base_convert($m[1], 36, 10));

        return $user && hash_equals(self::code($user), $code) ? $user : null;
    }

    /**
     * Offers come by email from now on (or not at all if they opted out of marketing).
     */
    public static function stop(User $user): void
    {
        $preferences = is_array($user->notification_preferences) ? $user->notification_preferences : [];
        data_set($preferences, 'customer.marketing', 'email');
        $user->forceFill(['notification_preferences' => $preferences])->save();
    }
}
