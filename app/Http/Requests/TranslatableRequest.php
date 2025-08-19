<?php

namespace App\Http\Requests;

use App\Services\LocalizationService;
use Illuminate\Foundation\Http\FormRequest;

abstract class TranslatableRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    abstract public function rules(): array;

    /**
     * Get translatable validation rules
     */
    protected function getTranslatableRules(array $fields, array $rules): array
    {
        $translatableRules = [];
        $availableLocales = LocalizationService::getAvailableLocales();
        
        foreach ($fields as $field) {
            foreach ($availableLocales as $locale) {
                $translatableRules[$field . '.' . $locale] = $rules;
            }
        }
        
        return $translatableRules;
    }

    /**
     * Get required translatable validation rules
     */
    protected function getRequiredTranslatableRules(array $fields, array $additionalRules = []): array
    {
        $rules = array_merge(['required', 'string', 'max:255'], $additionalRules);
        return $this->getTranslatableRules($fields, $rules);
    }

    /**
     * Get optional translatable validation rules
     */
    protected function getOptionalTranslatableRules(array $fields, array $additionalRules = []): array
    {
        $rules = array_merge(['nullable', 'string'], $additionalRules);
        return $this->getTranslatableRules($fields, $rules);
    }

    /**
     * Validate that at least one translation exists for required fields
     */
    protected function validateAtLeastOneTranslation(array $fields): void
    {
        $availableLocales = LocalizationService::getAvailableLocales();
        
        foreach ($fields as $field) {
            $hasTranslation = false;
            
            foreach ($availableLocales as $locale) {
                $value = $this->input($field . '.' . $locale);
                if (!empty($value)) {
                    $hasTranslation = true;
                    break;
                }
            }
            
            if (!$hasTranslation) {
                $this->validator->errors()->add(
                    $field,
                    "At least one translation is required for {$field}."
                );
            }
        }
    }

    /**
     * Prepare translatable data for model
     */
    protected function prepareTranslatableData(array $fields): array
    {
        $data = [];
        $availableLocales = LocalizationService::getAvailableLocales();
        
        foreach ($fields as $field) {
            $translations = [];
            
            foreach ($availableLocales as $locale) {
                $value = $this->input($field . '.' . $locale);
                if (!empty($value)) {
                    $translations[$locale] = $value;
                }
            }
            
            if (!empty($translations)) {
                $data[$field] = $translations;
            }
        }
        
        return $data;
    }

    /**
     * Get custom error messages for translatable fields
     */
    protected function getTranslatableErrorMessages(array $fields): array
    {
        $messages = [];
        $availableLocales = LocalizationService::getAvailableLocales();
        
        foreach ($fields as $field) {
            foreach ($availableLocales as $locale) {
                $messages[$field . '.' . $locale . '.required'] = "The {$field} field is required for {$locale}.";
                $messages[$field . '.' . $locale . '.string'] = "The {$field} field must be a string for {$locale}.";
                $messages[$field . '.' . $locale . '.max'] = "The {$field} field may not be greater than :max characters for {$locale}.";
            }
        }
        
        return $messages;
    }
} 