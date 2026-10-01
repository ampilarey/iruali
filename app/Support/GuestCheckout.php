<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Guest checkout is off unless the owner turns it on in Admin → Settings.
 */
class GuestCheckout
{
    public static function enabled(): bool
    {
        return filter_var(Setting::get('guest_checkout_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }
}
