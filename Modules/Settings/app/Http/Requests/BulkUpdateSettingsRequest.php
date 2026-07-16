<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BulkUpdateSettingsRequest — validation for the bulk-update payload.
 */
class BulkUpdateSettingsRequest extends FormRequest
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
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*' => ['array'],
            'settings.*.key' => ['required', 'string', 'max:150'],
            'settings.*.value' => ['nullable'],
            'settings.*.type' => ['nullable', 'string'],
        ];
    }
}
