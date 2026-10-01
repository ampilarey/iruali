<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The marketplace terms a shop signs up to, as placeholders filled from Settings at render time, so
 * the seller terms page (built-in text or the owner's own wording) always shows the current values.
 */
class SellerTerms
{
    public const SCHEDULES = ['weekly', 'fortnightly', 'monthly'];

    /**
     * Placeholder => current value, e.g. '{commission_rate}' => '10%'.
     *
     * @return array<string, string>
     */
    public static function placeholders(): array
    {
        $rate = (float) Setting::get('default_commission_rate');
        $day = trim((string) Setting::get('payout_day'));

        return [
            '{commission_rate}' => rtrim(rtrim(number_format($rate, 2), '0'), '.').'%',
            '{payout_schedule}' => self::scheduleLabel((string) Setting::get('payout_schedule')),
            '{payout_day}' => $day !== '' ? $day : __('the scheduled payout day'),
            '{late_shipment_days}' => (string) (int) Setting::get('late_shipment_days'),
            '{return_window_days}' => (string) Company::returnWindowDays(),
            '{trading_name}' => Company::tradingName(),
        ];
    }

    /**
     * What each placeholder means, for the admin legal-page editor.
     *
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return [
            '{commission_rate}' => __('Default commission (Settings → Marketplace)'),
            '{payout_schedule}' => __('How often shops are paid: weekly, every two weeks or monthly'),
            '{payout_day}' => __('The day payouts are made'),
            '{late_shipment_days}' => __('Days a shop has to ship after payment'),
            '{return_window_days}' => __('Days a customer has to ask for a return'),
            '{trading_name}' => __('The marketplace trading name'),
        ];
    }

    public static function replace(string $text): string
    {
        return strtr($text, self::placeholders());
    }

    public static function scheduleLabel(string $schedule): string
    {
        return [
            'weekly' => __('every week'),
            'fortnightly' => __('every two weeks'),
            'monthly' => __('every month'),
        ][$schedule] ?? $schedule;
    }
}
