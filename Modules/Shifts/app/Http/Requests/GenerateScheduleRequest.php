<?php

namespace Modules\Shifts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.required' => __('shifts.year_required'),
            'year.integer' => __('shifts.year_invalid'),
            'year.min' => __('shifts.year_min'),
            'year.max' => __('shifts.year_max'),
            'month.required' => __('shifts.month_required'),
            'month.integer' => __('shifts.month_invalid'),
            'month.min' => __('shifts.month_min'),
            'month.max' => __('shifts.month_max'),
        ];
    }
}
