<?php

namespace Modules\Vacations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceJustificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id', Rule::notIn([10000])],
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'missing_check_in' => ['nullable', 'boolean'],
            'missing_check_out' => ['nullable', 'boolean'],
            'late_arrival' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
