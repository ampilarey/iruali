<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Whether iruali takes new shop applications (Admin → Sellers → New shop applications):
 *  - open:   "Sell on iruali" leads to the application form (the default);
 *  - paused: the links stay, but lead to a page with the admins' message and "Tell me when you
 *            reopen" instead of the form;
 *  - hidden: the links come off the site (menus, footer, home page, help); anyone who still has
 *            the address sees the paused page.
 * While paused or hidden the form is refused on the server too. Applications already sent stay
 * in the queue and shops carry on as usual. With a reopening date, applications open by themselves
 * at the start of that day (Maldives time); `sellers:applications-reopen` then stores "open" and
 * emails the waiting list (SellerWaitlistService).
 */
class SellerApplications
{
    public const OPEN = 'open';

    public const PAUSED = 'paused';

    public const HIDDEN = 'hidden';

    public const STATUSES = [self::OPEN, self::PAUSED, self::HIDDEN];

    /** The admins' choice as saved, before a reopening day that has come is counted. */
    public static function stored(): string
    {
        $status = (string) Setting::get('seller_applications', self::OPEN);

        return in_array($status, self::STATUSES, true) ? $status : self::OPEN;
    }

    /** What applies now: a pause (or hiding) whose reopening day has come counts as open. */
    public static function status(): string
    {
        $status = self::stored();
        if ($status !== self::OPEN && ($reopens = self::reopensOn()) && $reopens->lte(today())) {
            return self::OPEN;
        }

        return $status;
    }

    public static function open(): bool
    {
        return self::status() === self::OPEN;
    }

    /** Should the site show its "Sell on iruali" links and calls to action? */
    public static function linksShown(): bool
    {
        return self::status() !== self::HIDDEN;
    }

    /** The day applications open again by themselves, when the admins set one. */
    public static function reopensOn(): ?Carbon
    {
        $date = (string) Setting::get('seller_applications_until', '');
        if ($date === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /** The admins' message in the visitor's language (the English one when there is no Dhivehi). */
    public static function message(): ?string
    {
        $english = trim((string) Setting::get('seller_applications_message_en', ''));
        $dhivehi = trim((string) Setting::get('seller_applications_message_dv', ''));
        $message = app()->getLocale() === 'dv' && $dhivehi !== '' ? $dhivehi : $english;

        return $message !== '' ? $message : null;
    }
}
