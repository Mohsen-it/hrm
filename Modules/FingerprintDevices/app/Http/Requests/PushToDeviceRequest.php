<?php

namespace Modules\FingerprintDevices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PushToDeviceRequest extends FormRequest
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
            'device_id' => ['required', 'integer', 'exists:fingerprint_devices,id'],

            'options' => ['required', 'array'],

            'options.push_users' => ['nullable', 'boolean'],
            'options.push_fingerprints' => ['nullable', 'boolean'],
            'options.push_face_photos' => ['nullable', 'boolean'],

            'options.user_ids' => ['nullable', 'array', 'max:3000'],
            'options.user_ids.*' => ['integer', 'exists:users,id'],

            'options.branch_id' => ['nullable', 'integer', 'exists:branches,id'],

            'options.select_mode' => ['nullable', Rule::in(['all', 'specific', 'branch', 'missing'])],
        ];
    }

    public function messages(): array
    {
        return [
            'device_id.required' => __('fingerprint_devices.validation.device_id_required'),
            'device_id.exists' => __('fingerprint_devices.validation.device_id_not_found'),
            'options.required' => __('fingerprint_devices.validation.options_required'),
            'options.user_ids.max' => __('fingerprint_devices.validation.user_ids_too_many'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $options = $this->input('options', []);
            $hasAny = ($options['push_users'] ?? false)
                || ($options['push_fingerprints'] ?? false)
                || ($options['push_face_photos'] ?? false);

            if (! $hasAny) {
                $validator->errors()->add(
                    'options',
                    __('fingerprint_devices.validation.at_least_one_push_option'),
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function pushOptions(): array
    {
        $data = $this->validated();
        $options = $data['options'] ?? [];

        // Normalize: if select_mode is set, populate user_ids accordingly
        $mode = $options['select_mode'] ?? 'all';
        if ($mode === 'all' && empty($options['user_ids']) && empty($options['branch_id'])) {
            // leave empty → DevicePushService will resolve all active employees
        }

        return $options;
    }
}
