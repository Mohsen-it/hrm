<?php

namespace Modules\Holidays\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreHolidayRequest — validate the create-holiday payload.
 *
 * The validation is intentionally permissive (CRUD is simple here);
 * the bulk of the business rules (recurring vs fixed, sane day/month
 * values) live inside `HolidayService::validatePayload()`.
 */
class StoreHolidayRequest extends FormRequest
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

        return [
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
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
