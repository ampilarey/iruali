<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Invoices are English documents. On Dhivehi pages each label also shows its Dhivehi, under the
 * English (resources/lang/dv.json).
 */
class InvoiceText
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function label(string $key, array $replace = []): HtmlString
    {
        $english = e(__($key, $replace, 'en'));
        if (app()->getLocale() !== 'dv') {
            return new HtmlString($english);
        }

        return new HtmlString($english.'<span class="dv" lang="dv"><bdi dir="rtl">'.e(__($key, $replace)).'</bdi></span>');
    }

    /**
     * "8.00" → "8", "12.50" → "12.5".
     */
    public static function rate(mixed $rate): string
    {
        return rtrim(rtrim(number_format((float) $rate, 2), '0'), '.');
    }
}
