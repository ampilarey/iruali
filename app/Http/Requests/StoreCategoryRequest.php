<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreCategoryRequest extends TranslatableRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $translatableRules = array_merge(
            $this->getRequiredTranslatableRules(['name']),
            $this->getOptionalTranslatableRules(['description', 'meta_title', 'meta_description'])
        );

        return array_merge($translatableRules, [
            'slug' => [
                'required',
                'string',
                'max:255',
                'unique:categories,slug'
            ],
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|in:active,inactive',
        ]);
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return array_merge(
            $this->getTranslatableErrorMessages(['name', 'description', 'meta_title', 'meta_description']),
            [
                'slug.unique' => 'This slug is already in use. Please choose a different one.',
                'parent_id.exists' => 'The selected parent category does not exist.',
                'image.max' => 'Category image must not exceed 2MB.',
                'image.mimes' => 'Category image must be a JPEG, PNG, JPG, or GIF file.',
                'status.in' => 'Status must be either active or inactive.',
            ]
        );
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name.en' => 'category name (English)',
            'name.dv' => 'category name (Dhivehi)',
            'description.en' => 'description (English)',
            'description.dv' => 'description (Dhivehi)',
            'meta_title.en' => 'meta title (English)',
            'meta_title.dv' => 'meta title (Dhivehi)',
            'meta_description.en' => 'meta description (English)',
            'meta_description.dv' => 'meta description (Dhivehi)',
            'parent_id' => 'parent category',
            'image' => 'category image',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateAtLeastOneTranslation(['name']);
        });
    }
} 