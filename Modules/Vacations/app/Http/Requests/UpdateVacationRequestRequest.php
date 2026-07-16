<?php

namespace Modules\Vacations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Vacations\Models\VacationType;

/**
 * UpdateVacationRequestRequest — validate edits to a still-pending request.
 *
 * Only requests in `pending` state can be edited. The decision
 * (approved | rejected | cancelled) is recorded through
 * `DecisionVacationRequestRequest`.
 */
class UpdateVacationRequestRequest extends FormRequest
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
            'vacation_type_id' => ['required', 'integer', Rule::in($types)],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
