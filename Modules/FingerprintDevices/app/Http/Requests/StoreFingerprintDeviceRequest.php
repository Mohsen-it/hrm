<?php

namespace Modules\FingerprintDevices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFingerprintDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create-fingerprint-devices');
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_type_id' => ['required', 'integer', 'exists:fingerprint_device_types,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:150'],
            'serial_number' => [
                'required', 'string', 'max:100',
                Rule::unique('fingerprint_devices', 'serial_number'),
            ],
            'ip_address' => ['required', 'ip', 'max:45'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'comm_key' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone'],
            'connection_type' => ['nullable', Rule::in(['tcp', 'udp'])],
            'timeout' => ['nullable', 'integer', 'min:5', 'max:120'],
            'status' => ['nullable', Rule::in(['online', 'offline', 'maintenance', 'deactivated'])],
            'capabilities' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_push_enabled' => ['nullable', 'boolean'],
            'push_url' => ['nullable', 'url', 'max:500', 'required_if:is_push_enabled,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'ip_address.ip' => __('fingerprint_devices.validation.ip_invalid'),
            'push_url.required_if' => __('fingerprint_devices.validation.push_url_required_when_enabled'),
            'timezone.timezone' => __('fingerprint_devices.validation.timezone_invalid'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        $data['port'] = $data['port'] ?? 4370;
        $data['comm_key'] = $data['comm_key'] ?? '';
        $data['timeout'] = $data['timeout'] ?? 30;
        $data['connection_type'] = $data['connection_type'] ?? 'tcp';
        $data['status'] = $data['status'] ?? 'offline';
        $data['is_push_enabled'] = (bool) ($data['is_push_enabled'] ?? false);

        return $data;
    }
}
