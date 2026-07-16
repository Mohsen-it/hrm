<?php

namespace Modules\FingerprintDevices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFingerprintDeviceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-fingerprint-device-types');
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

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        $data['default_port'] = $data['default_port'] ?? 4370;
        $data['max_fingerprints'] = $data['max_fingerprints'] ?? 3000;
        $data['max_users'] = $data['max_users'] ?? 10000;
        $data['supports_fingerprint'] = (bool) ($data['supports_fingerprint'] ?? true);
        $data['supports_face'] = (bool) ($data['supports_face'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
