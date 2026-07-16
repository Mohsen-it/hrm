<?php

namespace Modules\Vacations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DecisionVacationRequestRequest — capture the manager's verdict.
 *
 * Used for both `approve` and `reject` actions. The status itself is
 * decided by the route parameter (`decision = approve | reject`) — the
 * payload only carries the optional manager note.
 */
class DecisionVacationRequestRequest extends FormRequest
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
        return [
            'manager_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
