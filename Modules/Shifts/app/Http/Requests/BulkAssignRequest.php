<?php

namespace Modules\Shifts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BulkAssignRequest — validate the bulk-assign-employees payload.
 */
class BulkAssignRequest extends FormRequest
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
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:users,id'],
            'shift_category_id' => ['required', 'integer', 'exists:att_shift_categories,id'],
            'start_date' => ['required', 'date'],
        ];
    }
}
