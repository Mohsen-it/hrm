<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'company_id' => ['required', 'exists:companies,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'الكود مطلوب.',
            'code.max' => 'الكود لا يتجاوز 50 حرف.',
            'name.required' => 'الاسم مطلوب.',
            'name.max' => 'الاسم لا يتجاوز 100 حرف.',
            'company_id.required' => 'الشركة مطلوبة.',
            'company_id.exists' => 'الشركة غير موجودة.',
        ];
    }
}
