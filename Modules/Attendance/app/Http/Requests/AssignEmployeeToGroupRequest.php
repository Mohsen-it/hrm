<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignEmployeeToGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emp_id' => ['required', 'exists:users,id'],
            'enable_attendance' => ['sometimes', 'boolean'],
            'enable_schedule' => ['sometimes', 'boolean'],
            'enable_overtime' => ['sometimes', 'boolean'],
            'enable_holiday' => ['sometimes', 'boolean'],
            'enable_compensatory' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'emp_id.required' => 'الموظف مطلوب.',
            'emp_id.exists' => 'الموظف غير موجود.',
        ];
    }
}
