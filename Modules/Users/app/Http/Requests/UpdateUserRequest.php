<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * @property string|null $employee_code
 * @property string $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $full_name_ar
 * @property string|null $full_name_en
 * @property string $email
 * @property string|null $password
 * @property string|null $national_id
 * @property string|null $phone
 * @property string|null $phone2
 * @property string|null $date_of_birth
 * @property string|null $gender
 * @property string|null $marital_status
 * @property string|null $nationality
 * @property string|null $hire_date
 * @property string|null $termination_date
 * @property string|null $employment_type
 * @property string|null $job_title
 * @property string|null $work_location
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $postal_code
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_relation
 * @property string|null $bank_name
 * @property string|null $bank_account_number
 * @property string|null $iban
 * @property UploadedFile|null $avatar
 * @property int $status
 * @property bool $is_active_employee
 * @property bool $must_change_password
 * @property int|null $company_id
 * @property int|null $branch_id
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $grade_id
 * @property int|null $shift_id
 * @property int|null $manager_id
 * @property int|null $attendance_group_id
 * @property array|null $roles
 * @property array|null $permissions
 * @property array|null $shifts
 * @property array|null $zones
 * @property array|null $rotation_assignment
 * @property array|null $shift_category_assignment
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermissionTo('edit-users');
    }

    public function rules(): array
    {
        $userId = (int) $this->route('user');

        return [
            'employee_code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('users', 'employee_code')->ignore($userId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'full_name_ar' => ['nullable', 'string', 'max:255'],
            'full_name_en' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:20'],
            'phone2' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],
            'nationality' => ['nullable', 'string', 'max:50'],

            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contract,temporary,intern'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'work_location' => ['nullable', 'string', 'max:255'],

            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],

            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],

            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:50'],

            'avatar' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],

            'status' => ['required', 'integer', 'in:0,1'],
            'is_active_employee' => ['boolean'],
            'must_change_password' => ['boolean'],

            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'subordination_id' => ['nullable', 'integer', 'exists:subordinations,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'attendance_group_id' => ['nullable', 'integer', 'exists:att_attgroup,id'],

            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'shifts' => ['nullable', 'array'],
            'shifts.*.shift_id' => ['required_with:shifts', 'integer', 'exists:shifts,id'],
            'shifts.*.effective_from' => ['nullable', 'date'],
            'shifts.*.effective_to' => ['nullable', 'date', 'after_or_equal:shifts.*.effective_from'],
            'shifts.*.is_primary' => ['nullable', 'boolean'],
            'zones' => ['nullable', 'array'],

            'rotation_assignment' => ['nullable', 'array'],
            'rotation_assignment.action' => ['nullable', 'in:assign,transfer,unassign'],
            'rotation_assignment.rotation_id' => ['required_with:rotation_assignment', 'integer', 'exists:att_rotations,id'],
            'rotation_assignment.rotation_group_id' => ['required_if:rotation_assignment.action,assign,transfer', 'integer', 'exists:att_rotation_groups,id'],
            'rotation_assignment.start_date' => ['required_if:rotation_assignment.action,assign,transfer', 'date'],
            'rotation_assignment.end_date' => ['nullable', 'date'],

            'shift_category_assignment' => ['nullable', 'array'],
            'shift_category_assignment.action' => ['nullable', 'in:assign,transfer,unassign'],
            'shift_category_assignment.shift_category_id' => ['required_if:shift_category_assignment.action,assign,transfer', 'integer', 'exists:att_shift_categories,id'],
            'shift_category_assignment.start_date' => ['required_if:shift_category_assignment.action,assign,transfer', 'date'],
            'shift_category_assignment.end_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_code.unique' => __('users.employee_code_unique'),
            'name.required' => __('users.name_required'),
            'email.required' => __('users.email_required'),
            'email.email' => __('users.email_invalid'),
            'email.unique' => __('users.email_unique'),
            'password.min' => __('users.password_min'),
            'gender.in' => __('users.gender_invalid'),
            'marital_status.in' => __('users.marital_status_invalid'),
            'employment_type.in' => __('users.employment_type_invalid'),
            'termination_date.after_or_equal' => __('users.termination_date_after_hire'),
            'avatar.image' => __('users.avatar_invalid'),
            'avatar.mimes' => __('users.avatar_mimes'),
            'avatar.max' => __('users.avatar_max_size'),
            'status.required' => __('users.status_required'),
            'status.in' => __('users.status_invalid'),
            'company_id.exists' => __('users.company_id_exists'),
            'branch_id.exists' => __('users.branch_id_exists'),
            'department_id.exists' => __('users.department_id_exists'),
            'position_id.exists' => __('users.position_id_exists'),
            'grade_id.exists' => __('users.grade_id_exists'),
            'subordination_id.exists' => __('users.subordination_id_exists'),
            'shift_id.exists' => __('users.shift_id_exists'),
            'manager_id.exists' => __('users.manager_id_exists'),
            'attendance_group_id.exists' => __('users.attendance_group_id_exists'),
            'roles.*.exists' => __('users.role_invalid'),
            'permissions.*.exists' => __('users.permission_invalid'),
            'shifts.*.shift_id.required_with' => __('users.shift_id_required'),
            'shifts.*.shift_id.exists' => __('users.shift_id_exists'),
            'shifts.*.effective_to.after_or_equal' => __('users.effective_to_after_from'),
            'rotation_assignment.rotation_id.required_with' => __('users.rotation_id_required'),
            'rotation_assignment.rotation_group_id.required_if' => __('users.rotation_group_required'),
            'rotation_assignment.start_date.required_if' => __('users.rotation_start_date_required'),
            'shift_category_assignment.shift_category_id.required_if' => __('users.shift_category_required'),
            'shift_category_assignment.start_date.required_if' => __('users.shift_category_start_date_required'),
        ];
    }
}
