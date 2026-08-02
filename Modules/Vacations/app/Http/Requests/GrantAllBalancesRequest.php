<?php

namespace Modules\Vacations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GrantAllBalancesRequest — validate the bulk "grant default entitlement to
 * every active employee" payload that dispatches the
 * `RecalculateVacationBalances` job.
 */
class GrantAllBalancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('edit-vacation-balance');
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'vacation_type_id' => ['nullable', 'integer', 'exists:vacation_types,id'],
        ];
    }
}
