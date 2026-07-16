<?php

namespace Modules\Vacations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Vacations\Models\VacationType;

/**
 * StoreVacationRequestRequest — validate the open-request payload.
 *
 * The business rules (entitlement, advance notice, overlapping
 * requests, attachment requirement) live inside
 * `VacationRequestService::openRequest()`.
 */
class StoreVacationRequestRequest extends FormRequest
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
        $types = VacationType::query()
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        return [
            'user_id' => [
                'required', 'integer', 'exists:users,id',
                Rule::notIn([10000]), // super-admin cannot take vacations
            ],
            'vacation_type_id' => ['required', 'integer', Rule::in($types)],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Custom messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => __('vacations.start_date_must_be_today_or_later'),
            'end_date.after_or_equal' => __('vacations.end_date_must_be_after_start'),
        ];
    }
}
