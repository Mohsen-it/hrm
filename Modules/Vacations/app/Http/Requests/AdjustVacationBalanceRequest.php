<?php

namespace Modules\Vacations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustVacationBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('edit-vacation-balance');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'vacation_type_id' => ['required', 'exists:vacation_types,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'days_delta' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
