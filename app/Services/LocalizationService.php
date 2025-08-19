<?php

namespace App\Services;

use Illuminate\Support\Facades\App;

class LocalizationService
{
    /**
     * Get the current locale
     */
    public static function getCurrentLocale(): string
    {
        return App::getLocale();
    }

    /**
     * Get the fallback locale
     */
    public static function getFallbackLocale(): string
    {
        return config('app.fallback_locale', 'en');
    }

    /**
     * Get available locales
     */
    public static function getAvailableLocales(): array
    {
        return ['en', 'dv'];
    }

    /**
     * Get localized value with fallback
     */
    public static function getLocalizedValue($model, string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?: self::getCurrentLocale();
        $fallbackLocale = self::getFallbackLocale();

        // Try current locale first
        $value = $model->getTranslation($field, $locale, false);
        
        // If not found, try fallback locale
        if (!$value && $locale !== $fallbackLocale) {
            $value = $model->getTranslation($field, $fallbackLocale, false);
        }

        return $value;
    }

    /**
     * Get all translations for a field
     */
    public static function getAllTranslations($model, string $field): array
    {
        return $model->getTranslations($field);
    }

    /**
     * Check if translation exists for a field
     */
    public static function hasTranslation($model, string $field, ?string $locale = null): bool
    {
        $locale = $locale ?: self::getCurrentLocale();
        return $model->hasTranslation($field, $locale);
    }

    /**
     * Set translation for a field
     */
    public static function setTranslation($model, string $field, string $locale, string $value): void
    {
        $model->setTranslation($field, $locale, $value);
    }

    /**
     * Get localized name for any translatable model
     */
    public static function getLocalizedName($model): ?string
    {
        return self::getLocalizedValue($model, 'name');
    }

    /**
     * Get localized description for any translatable model
     */
    public static function getLocalizedDescription($model): ?string
    {
        return self::getLocalizedValue($model, 'description');
    }

    /**
     * Format localized data for API responses
     */
    public static function formatForApi($model, array $translatableFields = ['name', 'description']): array
    {
        $data = [];
        
        foreach ($translatableFields as $field) {
            if (in_array($field, $model->translatable ?? [])) {
                $data[$field] = self::getLocalizedValue($model, $field);
                $data[$field . '_translations'] = self::getAllTranslations($model, $field);
            }
        }

        return $data;
    }

    /**
     * Validate translation data
     */
    public static function validateTranslationData(array $data, array $requiredLocales = ['en']): array
    {
        $errors = [];
        
        foreach ($requiredLocales as $locale) {
            if (!isset($data[$locale]) || empty($data[$locale])) {
                $errors[] = "Translation for locale '{$locale}' is required.";
            }
        }

        return $errors;
    }
} 