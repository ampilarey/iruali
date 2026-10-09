<?php

namespace App\Rules;

use App\Services\GstService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A GST TIN in MIRA's format: 7 digits, "GST", 3 digits (1012345GST501). Spaces and letter case
 * are forgiven; store GstService::normaliseTin() of the value.
 */
class MiraTin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! GstService::isValidTin(GstService::normaliseTin($value))) {
            $fail(__('Enter the TIN in MIRA\'s format: 7 digits, then GST, then 3 digits (for example 1012345GST501).'));
        }
    }
}
