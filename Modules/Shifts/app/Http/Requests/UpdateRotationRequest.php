<?php

namespace Modules\Shifts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('edit-rotations');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'anchor_start_date' => ['sometimes', 'required', 'date'],
            'pattern' => ['sometimes', 'required', 'array', 'min:2'],
            'pattern.*' => ['required', 'in:0,1'],
            'number_of_groups' => ['sometimes', 'required', 'integer', 'min:1', 'max:26'],
            'time_schedule_id' => ['nullable', 'integer', 'exists:att_time_schedules,id'],
            'overtime_enabled' => ['boolean'],
            'work_on_holidays' => ['boolean'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'color' => ['nullable', 'string', 'max:7'],
            'in_ahead_margin' => ['nullable', 'date_format:H:i'],
            'in_above_margin' => ['nullable', 'date_format:H:i'],
            'out_ahead_margin' => ['nullable', 'date_format:H:i'],
            'out_above_margin' => ['nullable', 'date_format:H:i'],
        ];
    }
}
