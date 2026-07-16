<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreSettingRequest — validation for the create-setting payload.
 */
class StoreSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = ['string', 'int', 'integer', 'float', 'bool', 'boolean', 'json', 'array'];
        $groups = ['general', 'attendance', 'branding', 'security', 'integrations'];

        return [
            'key' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9_.\-]+$/i', 'unique:settings,key'],
            'value' => ['nullable'],
            'type' => ['nullable', 'string', Rule::in($types)],
            'group' => ['nullable', 'string', Rule::in($groups)],
            'name_ar' => ['nullable', 'string', 'max:200'],
            'name_en' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['nullable', 'boolean'],
            'is_encrypted' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
