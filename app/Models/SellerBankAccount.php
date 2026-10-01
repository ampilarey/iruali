<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The bank account iruali pays a shop's earnings to. Payouts and bank files are refused for shops
 * without one; an admin can mark it verified once it has been checked.
 */
class SellerBankAccount extends Model
{
    public const BANKS = ['bml', 'mib', 'other'];

    protected $fillable = ['user_id', 'bank', 'bank_name_other', 'account_name', 'account_number', 'currency', 'verified_at'];

    protected $casts = ['verified_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function bankLabels(): array
    {
        return ['bml' => __('Bank of Maldives (BML)'), 'mib' => __('Maldives Islamic Bank (MIB)'), 'other' => __('Other bank')];
    }

    /**
     * Validation for the bank account form. BML account numbers are 13 digits starting 7730 or 7770;
     * MIB account numbers are 16 digits starting 90; other banks are free text.
     */
    public static function rules(string $bank): array
    {
        return [
            'bank' => ['required', 'in:'.implode(',', self::BANKS)],
            'bank_name_other' => ['nullable', 'required_if:bank,other', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_number' => [
                'required', 'string', 'max:40',
                function (string $attribute, mixed $value, \Closure $fail) use ($bank) {
                    $digits = preg_replace('/\s+/', '', (string) $value);
                    if ($bank === 'bml' && ! preg_match('/^(7730|7770)\d{9}$/', $digits)) {
                        $fail(__('A BML account number is 13 digits and starts with 7730 or 7770.'));
                    } elseif ($bank === 'mib' && ! preg_match('/^90\d{14}$/', $digits)) {
                        $fail(__('An MIB account number is 16 digits and starts with 90.'));
                    } elseif ($bank === 'other' && ! preg_match('/^[A-Za-z0-9 -]{4,40}$/', $digits)) {
                        $fail(__('Enter the account number using letters, digits and dashes only.'));
                    }
                },
            ],
        ];
    }

    public function bankName(): string
    {
        return $this->bank === 'other' ? (string) $this->bank_name_other : (self::bankLabels()[$this->bank] ?? $this->bank);
    }

    /**
     * The account number with everything but the last four characters hidden.
     */
    public function maskedNumber(): string
    {
        return self::mask((string) $this->account_number);
    }

    public static function mask(string $number): string
    {
        return strlen($number) <= 4 ? $number : str_repeat('•', strlen($number) - 4).substr($number, -4);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
