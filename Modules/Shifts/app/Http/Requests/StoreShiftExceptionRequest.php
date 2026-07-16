<?php

namespace Modules\Shifts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreShiftExceptionRequest — validate a leave/mission/swap interceptor entry.
 */
class StoreShiftExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('edit-shift-categories');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'exception_type' => ['required', Rule::in(['leave', 'mission', 'swap', 'training', 'other'])],
            'from_date' => ['required', 'date', 'before_or_equal:to_date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ];
    }
}
