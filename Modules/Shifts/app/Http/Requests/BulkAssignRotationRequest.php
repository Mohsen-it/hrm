<?php

namespace Modules\Shifts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkAssignRotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('assign-employees-to-rotation');
    }

    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'integer', 'exists:users,id'],
            'rotation_id' => ['required', 'integer', 'exists:att_rotations,id'],
            'rotation_group_id' => ['required', 'integer', 'exists:att_rotation_groups,id'],
            'start_date' => ['required', 'date'],
        ];
    }
}
