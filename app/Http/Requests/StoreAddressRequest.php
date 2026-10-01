<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A saved delivery address: the island comes from the list or, for one that is not listed, as free text.
 */
class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'label' => 'nullable|string|max:50',
            'recipient_name' => 'required|string|max:120',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,15}$/'],
            'island_id' => 'nullable|integer|exists:islands,id',
            'island' => 'required_without:island_id|nullable|string|max:100',
            'atoll' => 'nullable|string|max:100',
            'house_name_or_street' => 'required|string|max:255',
            'ward' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'is_default' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_name.required' => __('Please enter who the delivery is for.'),
            'phone.required' => __('Please enter a phone number we can reach you on for delivery.'),
            'phone.regex' => __('Please enter a valid phone number (digits only, e.g. 7771234).'),
            'island.required_without' => __('Please pick your island, or type its name.'),
            'house_name_or_street.required' => __('Please enter the house name or street.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => filled($this->input('label')) ? trim($this->input('label')) : null,
            'recipient_name' => trim((string) $this->input('recipient_name')),
            'phone' => filled($this->input('phone')) ? preg_replace('/\s+/', '', trim($this->input('phone'))) : null,
            'island_id' => filled($this->input('island_id')) ? $this->input('island_id') : null,
            'island' => filled($this->input('island')) ? trim($this->input('island')) : null,
            'atoll' => filled($this->input('atoll')) ? trim($this->input('atoll')) : null,
            'house_name_or_street' => trim((string) $this->input('house_name_or_street')),
            'ward' => filled($this->input('ward')) ? trim($this->input('ward')) : null,
            'postal_code' => filled($this->input('postal_code')) ? trim($this->input('postal_code')) : null,
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    /**
     * The address fields, ready for Address::fill(). The island record (when picked) is copied in by Address::syncIsland().
     */
    public function addressData(): array
    {
        $data = $this->validated();
        $data['country'] = 'Maldives';
        $data['island'] = $data['island'] ?? '';

        return $data;
    }
}
