<?php

namespace Modules\FingerprintDevices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserFingerprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit-fingerprint-devices');
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quality' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_master' => ['nullable', 'boolean'],
            'captured_at' => ['nullable', 'date'],
        ];
    }
}
