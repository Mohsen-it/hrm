<?php

namespace Modules\Positions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property int $company_id
 * @property int $branch_id
 * @property int|null $department_id
 * @property string $position_code
 * @property string $position_name
 * @property string|null $description
 * @property float|null $min_salary
 * @property float|null $max_salary
 * @property string|null $requirements
 * @property int $status
 */
class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermissionTo('create-positions');
    }

    public function rules(): array
    {
        $departmentId = $this->input('department_id', 0);

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_code' => [
                'required', 'string', 'max:50',
                Rule::unique('positions', 'position_code')
                    ->where('department_id', $departmentId),
            ],
            'position_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'requirements' => ['nullable', 'string'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => __('positions.company_id_required'),
            'company_id.exists' => __('positions.company_id_exists'),
            'branch_id.required' => __('positions.branch_id_required'),
            'branch_id.exists' => __('positions.branch_id_exists'),
            'department_id.exists' => __('positions.department_id_exists'),
            'position_code.required' => __('positions.position_code_required'),
            'position_code.unique' => __('positions.position_code_unique'),
            'position_name.required' => __('positions.position_name_required'),
            'min_salary.numeric' => __('positions.min_salary_numeric'),
            'min_salary.min' => __('positions.min_salary_min'),
            'max_salary.numeric' => __('positions.max_salary_numeric'),
            'max_salary.min' => __('positions.max_salary_min'),
            'max_salary.gte' => __('positions.max_salary_gte'),
            'status.required' => __('positions.status_required'),
            'status.in' => __('positions.status_invalid'),
        ];
    }
}
