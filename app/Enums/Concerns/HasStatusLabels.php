<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for the backed status enums: the stored string values, a translated label and a
 * Tailwind badge class, plus lookups by raw string for the many places that still hold the
 * database value rather than a case.
 */
trait HasStatusLabels
{
    /**
     * The values exactly as stored in the database, in display order.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Translated label for a stored value; unknown or legacy values are shown tidied up, never hidden.
     */
    public static function labelFor(?string $status): string
    {
        $case = $status === null ? null : self::tryFrom($status);

        return $case ? $case->label() : ucfirst(str_replace('_', ' ', (string) $status));
    }

    /**
     * Badge classes for a stored value; unknown values get the neutral grey badge.
     */
    public static function badgeFor(?string $status): string
    {
        $case = $status === null ? null : self::tryFrom($status);

        return $case ? $case->badgeClass() : 'bg-gray-100 text-gray-800';
    }

    /**
     * All labels keyed by stored value, for filter dropdowns.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }
}
