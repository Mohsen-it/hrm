<?php

namespace Modules\Zones\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AssignBranchesRequest — validation for the branch-assignment payload.
 */
class AssignBranchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branches' => ['required', 'array', 'min:1'],
            'branches.*' => ['array'],
            'branches.*.branch_id' => ['required', 'integer', 'exists:branches,id'],
            'branches.*.is_primary' => ['nullable', 'boolean'],
            'branches.*.priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'branches.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
