<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A mobile number no other account has confirmed, however it is typed ("+960 777 1234" and
 * "7771234" are the same number). Sign-in by text trusts the confirmed holder of a number
 * (User::findByVerifiedPhone()), so a number confirmed on one account can't be put on another.
 */
class PhoneNotTaken implements ValidationRule
{
    public function __construct(protected ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }
        $holder = User::findByVerifiedPhone((string) $value);
        if ($holder && $holder->id !== $this->ignoreUserId) {
            $fail(__('This mobile number is already confirmed on another account. Sign in with it instead, or use another number.'));
        }
    }
}
