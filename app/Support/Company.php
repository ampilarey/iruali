<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The business details shown on policy pages, the footer and checkout (BML website requirement #3).
 * Everything comes from Admin → Settings; blank values are simply left out.
 */
class Company
{
    public static function tradingName(): string
    {
        return (string) (Setting::get('company_trading_name') ?: 'iruali');
    }

    public static function legalName(): ?string
    {
        return self::value('company_legal_name');
    }

    public static function registrationNo(): ?string
    {
        return self::value('company_registration_no');
    }

    public static function address(): ?string
    {
        return self::value('company_address');
    }

    public static function postalAddress(): ?string
    {
        return self::value('company_postal_address') ?? self::address();
    }

    public static function email(): ?string
    {
        return self::value('contact_email');
    }

    public static function phone(): ?string
    {
        return self::value('contact_phone');
    }

    public static function hours(): ?string
    {
        return self::value('customer_service_hours');
    }

    public static function returnWindowDays(): int
    {
        return (int) Setting::get('return_window_days');
    }

    public static function country(): string
    {
        return 'Maldives';
    }

    public static function currency(): string
    {
        return 'MVR (Maldivian Rufiyaa)';
    }

    /**
     * Details BML expects that haven't been filled in yet (shown to admins as a checklist).
     *
     * @return array<string, string>
     */
    public static function missing(): array
    {
        return array_filter([
            'company_legal_name' => self::legalName() ? null : 'Registered business name',
            'company_registration_no' => self::registrationNo() ? null : 'Business registration number',
            'company_address' => self::address() ? null : 'Business address (permanent establishment)',
            'contact_email' => self::email() ? null : 'Customer service email',
            'contact_phone' => self::phone() ? null : 'Customer service phone (with +960)',
        ]);
    }

    protected static function value(string $key): ?string
    {
        $value = trim((string) Setting::get($key));

        return $value === '' ? null : $value;
    }
}
