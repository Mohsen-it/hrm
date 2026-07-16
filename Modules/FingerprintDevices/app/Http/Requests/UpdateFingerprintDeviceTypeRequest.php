<?php

namespace Modules\FingerprintDevices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFingerprintDeviceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit-fingerprint-device-types');
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'manufacturer' => ['required', 'string', 'max:100'],
            'protocol' => ['nullable', 'string', 'max:30'],
            'sdk_version' => ['nullable', 'string', 'max:30'],
            'default_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'supports_fingerprint' => ['nullable', 'boolean'],
            'supports_face' => ['nullable', 'boolean'],
            'max_fingerprints' => ['nullable', 'integer', 'min:0'],
            'max_users' => ['nullable', 'integer', 'min:0'],
            'connection_params' => ['nullable', 'array'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
