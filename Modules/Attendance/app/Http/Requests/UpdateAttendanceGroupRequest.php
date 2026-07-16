<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.max' => 'الكود لا يتجاوز 50 حرف.',
            'name.max' => 'الاسم لا يتجاوز 100 حرف.',
            'status.in' => 'الحالة يجب أن تكون 0 أو 1.',
        ];
    }
}
