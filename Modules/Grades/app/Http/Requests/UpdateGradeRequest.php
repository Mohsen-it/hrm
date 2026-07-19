<?php

namespace Modules\Grades\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property int $company_id
 * @property string $grade_code
 * @property string $grade_name
 * @property int $level
 * @property float|null $min_salary
 * @property float|null $max_salary
 * @property string|null $description
 * @property int $status
 */
class UpdateGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermissionTo('edit-grades');
    }

    public function rules(): array
    {
        $gradeId = (int) $this->route('grade');
        $companyId = $this->input('company_id', 0);

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'grade_code' => [
                'required', 'string', 'max:50',
                Rule::unique('grades', 'grade_code')
                    ->ignore($gradeId)
                    ->where('company_id', $companyId),
            ],
            'grade_name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:1', 'max:255'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => __('grades.company_id_required'),
            'company_id.exists' => __('grades.company_id_exists'),
            'grade_code.required' => __('grades.grade_code_required'),
            'grade_code.unique' => __('grades.grade_code_unique'),
            'grade_name.required' => __('grades.grade_name_required'),
            'level.required' => __('grades.level_required'),
            'level.integer' => __('grades.level_integer'),
            'level.min' => __('grades.level_min'),
            'level.max' => __('grades.level_max'),
            'min_salary.numeric' => __('grades.min_salary_numeric'),
            'min_salary.min' => __('grades.min_salary_min'),
            'max_salary.numeric' => __('grades.max_salary_numeric'),
            'max_salary.min' => __('grades.max_salary_min'),
            'max_salary.gte' => __('grades.max_salary_gte'),
            'status.required' => __('grades.status_required'),
            'status.in' => __('grades.status_invalid'),
        ];
    }
}
