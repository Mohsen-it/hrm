<?php

namespace Modules\Vacations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateVacationTypeRequest — validate the update-vacation-type payload.
 *
 * Mirrors `StoreVacationTypeRequest`; the `code` uniqueness check is
 * scoped via the rule's `ignore` parameter so re-submitting the same
 * row does not collide.
 */
class UpdateVacationTypeRequest extends FormRequest
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
        $typeId = (int) $this->route('vacationType');

        return [
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('vacation_types', 'code')->ignore($typeId)->whereNull('deleted_at'),
            ],
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
            'default_days_per_year' => ['nullable', 'integer', 'min:0', 'max:366'],
            'max_days_per_request' => ['nullable', 'integer', 'min:0', 'max:366'],
            'max_carry_days' => ['nullable', 'integer', 'min:0', 'max:366'],
            'advance_notice_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_paid' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'requires_attachment' => ['nullable', 'boolean'],
            'deducts_from_balance' => ['nullable', 'boolean'],
            'counts_weekends' => ['nullable', 'boolean'],
            'counts_holidays' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
