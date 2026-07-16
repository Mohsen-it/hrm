<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_id' => ['required', 'exists:att_attgroup,id'],
            'shift_id' => ['required', 'exists:att_attshift,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'group_id.required' => 'الفئة مطلوبة.',
            'group_id.exists' => 'الفئة غير موجودة.',
            'shift_id.required' => 'المناوبة مطلوبة.',
            'shift_id.exists' => 'المناوبة غير موجودة.',
            'start_date.required' => 'تاريخ البداية مطلوب.',
            'end_date.required' => 'تاريخ النهاية مطلوب.',
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية.',
        ];
    }
}
