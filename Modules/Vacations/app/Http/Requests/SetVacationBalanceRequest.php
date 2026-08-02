<?php

namespace Modules\Vacations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SetVacationBalanceRequest — validate the absolute "set entitled days"
 * payload used by the HR balances matrix.
 */
class SetVacationBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('edit-vacation-balance');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'vacation_type_id' => ['required', 'integer', 'exists:vacation_types,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'days' => ['required', 'integer', 'min:0', 'max:366'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
