<?php

namespace Modules\Holidays\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateHolidayRequest — validate the update-holiday payload.
 *
 * Mirrors `StoreHolidayRequest`; the `code` uniqueness check is scoped
 * via the rule's `ignore` parameter so re-submitting the same row does
 * not collide.
 */
class UpdateHolidayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categories = ['public', 'religious', 'national', 'company', 'weekend'];
        $holidayId = (int) $this->route('holiday');

        return [
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('holidays', 'code')->ignore($holidayId)->whereNull('deleted_at'),
            ],
            'is_recurring' => ['nullable', 'boolean'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'recurring_month' => ['nullable', 'integer', 'between:1,12'],
            'recurring_day' => ['nullable', 'integer', 'between:1,31'],
            'category' => ['nullable', Rule::in($categories)],
            'is_paid' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'applies_to_all' => ['nullable', 'boolean'],
            'applies_to_branches' => ['nullable', 'array'],
            'applies_to_branches.*' => ['integer', 'exists:branches,id'],
            'applies_to_departments' => ['nullable', 'array'],
            'applies_to_departments.*' => ['integer', 'exists:departments,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
