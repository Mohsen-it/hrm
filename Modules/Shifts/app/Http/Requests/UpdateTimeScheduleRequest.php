<?php

namespace Modules\Shifts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateTimeScheduleRequest — validate the update-time-schedule payload.
 */
class UpdateTimeScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('edit-time-schedules');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'in_time' => ['sometimes', 'date_format:H:i'],
            'out_time' => ['sometimes', 'date_format:H:i'],
            'is_multi_day' => ['boolean'],
            'late_margin' => ['integer', 'min:0'],
            'early_margin' => ['integer', 'min:0'],
            'in_ahead_margin' => ['nullable', 'date_format:H:i'],
            'in_above_margin' => ['nullable', 'date_format:H:i'],
            'out_ahead_margin' => ['nullable', 'date_format:H:i'],
            'out_above_margin' => ['nullable', 'date_format:H:i'],
            'breaks' => ['nullable', 'array'],
            'breaks.*.break_start' => ['nullable', 'date_format:H:i'],
            'breaks.*.duration' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
