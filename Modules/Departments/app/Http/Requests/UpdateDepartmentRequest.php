<?php

namespace Modules\Departments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property int $company_id
 * @property int $branch_id
 * @property int|null $parent_id
 * @property int|null $manager_id
 * @property string $department_code
 * @property string $department_name
 * @property string|null $description
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $location
 * @property int $status
 */
class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermissionTo('edit-departments');
    }

    public function rules(): array
    {
        $departmentId = (int) $this->route('department');
        $branchId = $this->input('branch_id', 0);

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'parent_id' => ['nullable', 'integer', 'exists:departments,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'department_code' => [
                'required', 'string', 'max:50',
                Rule::unique('departments', 'department_code')
                    ->ignore($departmentId)
                    ->where('branch_id', $branchId),
            ],
            'department_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => __('departments.company_id_required'),
            'company_id.exists' => __('departments.company_id_exists'),
            'branch_id.required' => __('departments.branch_id_required'),
            'branch_id.exists' => __('departments.branch_id_exists'),
            'parent_id.exists' => __('departments.parent_id_exists'),
            'manager_id.exists' => __('departments.manager_id_exists'),
            'department_code.required' => __('departments.department_code_required'),
            'department_code.unique' => __('departments.department_code_unique'),
            'department_name.required' => __('departments.department_name_required'),
            'email.email' => __('departments.email_invalid'),
            'status.required' => __('departments.status_required'),
            'status.in' => __('departments.status_invalid'),
        ];
    }
}
