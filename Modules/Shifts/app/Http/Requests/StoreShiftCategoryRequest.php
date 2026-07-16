<?php

namespace Modules\Shifts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreShiftCategoryRequest — validate the create-shift-category payload.
 */
class StoreShiftCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('create-shift-categories');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', Rule::in(['cyclic', 'weekly', 'hours'])],
            'overtime_enabled' => ['boolean'],
            'fingerprint_enabled' => ['boolean'],
            'work_on_holidays' => ['boolean'],
            'work_on_weekends' => ['boolean'],
            'color' => ['nullable', 'string', 'max:7'],
            'anchor_start_date' => ['nullable', 'date'],
            'cycle_length' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_dynamic' => ['nullable', 'boolean'],
        ];

        if ($type === 'cyclic') {
            $rules['work_days'] = ['required', 'integer', 'min:1'];
            $rules['rest_days'] = ['required', 'integer', 'min:0'];
        } elseif ($type === 'weekly') {
            $rules['work_days_json'] = ['required', 'array', 'min:1'];
            $rules['weekend_days_json'] = ['nullable', 'array'];
        } elseif ($type === 'hours') {
            $rules['required_hours'] = ['required', 'numeric', 'min:0.01'];
            $rules['period_type'] = ['required', 'in:daily,weekly,monthly'];
        }

        return $rules;
    }
}
