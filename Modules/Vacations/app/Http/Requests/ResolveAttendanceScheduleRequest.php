<?php

namespace Modules\Vacations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveAttendanceScheduleRequest extends FormRequest
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
        ];
    }
}
