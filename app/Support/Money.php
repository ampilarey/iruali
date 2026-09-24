<?php

namespace App\Support;

/**
 * Rufiyaa formatting per the iruali brand: "MVR 1,250.00" in English,
 * "ރ. 1,250.00" in Dhivehi (kept left-to-right with an LRM mark).
 */
class Money
{
    public static function prefix(): string
    {
        return app()->getLocale() === 'dv' ? "ރ.\u{200E} " : 'MVR ';
    }

    public static function format(float|int|string|null $amount): string
    {
        return static::prefix().number_format((float) $amount, 2);
    }
}
