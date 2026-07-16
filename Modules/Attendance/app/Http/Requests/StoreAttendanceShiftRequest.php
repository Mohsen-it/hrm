<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alias' => ['required', 'string', 'max:50'],
            'cycle_unit' => ['required', 'integer', 'in:1,2,3'],
            'shift_cycle' => ['required', 'integer', 'min:1'],
            'company_id' => ['required', 'exists:companies,id'],
            'work_weekend' => ['sometimes', 'boolean'],
            'work_day_off' => ['sometimes', 'boolean'],
            'frequency' => ['sometimes', 'integer', 'min:1'],
            'details' => ['sometimes', 'array'],
            'details.*.time_interval_id' => ['required_with:details', 'exists:att_timeinterval,id'],
            'details.*.day_index' => ['required_with:details', 'integer', 'min:0', 'max:6'],
            'details.*.in_time' => ['required_with:details', 'date_format:H:i'],
            'details.*.out_time' => ['required_with:details', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'alias.required' => 'اسم المناوبة مطلوب.',
            'cycle_unit.required' => 'وحدة الدورة مطلوبة.',
            'shift_cycle.required' => 'طول الدورة مطلوب.',
            'company_id.required' => 'الشركة مطلوبة.',
        ];
    }
}
