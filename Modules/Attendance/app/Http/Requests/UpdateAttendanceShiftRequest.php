<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alias' => ['sometimes', 'string', 'max:50'],
            'cycle_unit' => ['sometimes', 'integer', 'in:1,2,3'],
            'shift_cycle' => ['sometimes', 'integer', 'min:1'],
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
}
