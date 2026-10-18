<?php

namespace App\Support;

use App\Models\Island;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Prayer times for an island, worked out from its latitude and longitude (the usual astronomical
 * method: Fajr and Isha at a sun angle of 18°, Asr when a shadow is its length plus the noon
 * shadow). A calculation, so a minute or two from the official times; shown as a small bar when
 * switched on (Admin → Settings → Storefront).
 */
class PrayerTimes
{
    public const NAMES = ['fajr' => 'Fajr', 'sunrise' => 'Sunrise', 'dhuhr' => 'Dhuhr', 'asr' => 'Asr', 'maghrib' => 'Maghrib', 'isha' => 'Isha'];

    /** Malé, when the shopper has not chosen an island or it has no location */
    public const MALE = [4.1755, 73.5093];

    public static function enabled(): bool
    {
        return filter_var(Setting::get('prayer_times_enabled', 0), FILTER_VALIDATE_BOOL);
    }

    /**
     * Today's times for the island (or Malé), cached for the day.
     *
     * @return array{place: string, times: array<string, string>}
     */
    public static function forIsland(?Island $island, ?Carbon $date = null): array
    {
        $date = ($date ?? now('Indian/Maldives'))->copy()->setTimezone('Indian/Maldives');
        $located = $island && $island->latitude !== null && $island->longitude !== null;
        [$lat, $lng] = $located ? [(float) $island->latitude, (float) $island->longitude] : self::MALE;
        $place = $located ? (string) $island->localized_name : (app()->getLocale() === 'dv' ? 'މާލެ' : 'Malé');

        $times = Cache::remember('prayer:'.$date->toDateString().':'.round($lat, 3).':'.round($lng, 3), now()->addHours(6), fn () => self::calculate($lat, $lng, $date));

        return ['place' => $place, 'times' => $times];
    }

    /**
     * @return array<string, string> name => "HH:MM" (Maldives time, UTC+5)
     */
    public static function calculate(float $lat, float $lng, Carbon $date): array
    {
        $timezone = 5.0;
        $fajrAngle = (float) Setting::get('prayer_fajr_angle', 18);
        $ishaAngle = (float) Setting::get('prayer_isha_angle', 18);

        // Julian day at midnight (valid 1901-2099; no calendar extension needed), then local noon
        [$y, $m, $d] = [$date->year, $date->month, $date->day];
        $jd = 367 * $y - intdiv(7 * ($y + intdiv($m + 9, 12)), 4) + intdiv(275 * $m, 9) + $d + 1721013.5 - $lng / 360;
        [$decl, $eqt] = self::sun($jd + 0.5);

        $noon = 12 + $timezone - $lng / 15 - $eqt;
        $hourAngle = function (float $angle) use ($lat, $decl): float {
            $cos = (-sin(deg2rad($angle)) - sin(deg2rad($lat)) * sin(deg2rad($decl))) / (cos(deg2rad($lat)) * cos(deg2rad($decl)));

            return rad2deg(acos(max(-1, min(1, $cos)))) / 15;
        };
        $asrAngle = -rad2deg(atan(1 / (1 + tan(deg2rad(abs($lat - $decl))))));

        $times = [
            'fajr' => $noon - $hourAngle($fajrAngle),
            'sunrise' => $noon - $hourAngle(0.833),
            'dhuhr' => $noon + 1 / 60, // a minute after the sun passes its highest point
            'asr' => $noon + $hourAngle($asrAngle),
            'maghrib' => $noon + $hourAngle(0.833) + 1 / 60,
            'isha' => $noon + $hourAngle($ishaAngle),
        ];

        return array_map(function (float $hours) {
            $minutes = (int) round($hours * 60);

            return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
        }, $times);
    }

    /**
     * The sun's declination and the equation of time (hours) on a Julian day.
     *
     * @return array{0: float, 1: float}
     */
    protected static function sun(float $jd): array
    {
        $d = $jd - 2451545.0;
        $g = deg2rad(fmod(357.529 + 0.98560028 * $d, 360));
        $q = fmod(280.459 + 0.98564736 * $d, 360);
        $l = deg2rad(fmod($q + 1.915 * sin($g) + 0.020 * sin(2 * $g), 360));
        $e = deg2rad(23.439 - 0.00000036 * $d);
        $ra = rad2deg(atan2(cos($e) * sin($l), cos($l))) / 15;
        $ra = fmod($ra + 24, 24);
        $decl = rad2deg(asin(sin($e) * sin($l)));
        $eqt = $q / 15 - $ra;
        $eqt = $eqt - 24 * round($eqt / 24);

        return [$decl, $eqt];
    }

    /**
     * The next prayer from now: [name, time], or Fajr tomorrow after Isha.
     *
     * @param  array<string, string>  $times
     * @return array{0: string, 1: string}
     */
    public static function next(array $times, ?Carbon $now = null): array
    {
        $clock = ($now ?? now('Indian/Maldives'))->copy()->setTimezone('Indian/Maldives')->format('H:i');
        foreach (['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'] as $name) {
            if ($times[$name] > $clock) {
                return [$name, $times[$name]];
            }
        }

        return ['fajr', $times['fajr']];
    }
}
