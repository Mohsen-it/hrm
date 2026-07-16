<?php

namespace Modules\AttendanceIntegration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDevicePunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'SN' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'api_token' => ['nullable', 'string', 'max:128'],
            'user_id' => ['nullable', 'string', 'max:50'],
            'timestamp' => ['nullable', 'string', 'max:30'],
            'punch_type' => ['nullable', 'string', 'in:check_in,check_out,auto,break_in,break_out'],
            'status' => ['nullable', 'integer', 'min:0', 'max:255'],
            'work_code' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'Body' => ['nullable', 'string', 'max:524288'],
            'attendance' => ['nullable', 'array', 'max:500'],
            'attendance.*.user_id' => ['required', 'string', 'max:50'],
            'attendance.*.timestamp' => ['nullable', 'string', 'max:30'],
            'attendance.*.status' => ['nullable', 'integer', 'min:0', 'max:255'],
            'attendance.*.punch_type' => ['nullable', 'string', 'in:check_in,check_out,auto,break_in,break_out'],
            'punches' => ['nullable', 'array', 'max:500'],
            'punches.*.user_id' => ['required', 'string', 'max:50'],
            'punches.*.timestamp' => ['nullable', 'string', 'max:30'],
            'punches.*.status' => ['nullable', 'integer', 'min:0', 'max:255'],
            'punches.*.punch_type' => ['nullable', 'string', 'in:check_in,check_out,auto,break_in,break_out'],
        ];
    }

    public function messages(): array
    {
        return [
            'SN.max' => 'Serial number must not exceed 100 characters.',
            'user_id.required_without_all' => 'A user_id, attendance array, punches array, or Body is required.',
            'Body.max' => 'ADMS body payload must not exceed 512 KB.',
            'attendance.max' => 'Attendance batch must not exceed 500 records per request.',
            'punches.max' => 'Punches batch must not exceed 500 records per request.',
            'attendance.*.user_id.required' => 'Each attendance record must have a user_id.',
            'punches.*.user_id.required' => 'Each punch record must have a user_id.',
        ];
    }
}
