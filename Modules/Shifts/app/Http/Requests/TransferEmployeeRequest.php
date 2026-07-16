<?php

namespace Modules\Shifts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TransferEmployeeRequest — validate the transfer-employee-to-category payload.
 */
class TransferEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('assign-employees-to-category');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'new_category_id' => ['required', 'integer', 'exists:att_shift_categories,id'],
            'effective_date' => ['required', 'date'],
        ];
    }
}
