<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreRawAttendanceLogRequest — validate a single raw log created from the
 * UI (manual entry by an operator). The `punch_time` accepts any
 * parseable date format; the service normalises it to a Y-m-d H:i:s string.
 */
class StoreRawAttendanceLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'device_id' => ['nullable', 'integer'],
            'device_user_id' => ['nullable', 'string', 'max:100'],
            'punch_time' => ['required', 'date'],
            'punch_type' => ['nullable', Rule::in(['check_in', 'check_out', 'unknown'])],
            'verify_type' => ['nullable', Rule::in(['fingerprint', 'card', 'password', 'face'])],
            'work_code' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'source' => ['nullable', Rule::in(['device', 'adms', 'manual', 'api'])],
            'ip_address' => ['nullable', 'string', 'max:45'],
        ];
    }
}
