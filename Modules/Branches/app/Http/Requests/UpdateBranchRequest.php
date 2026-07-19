<?php

namespace Modules\Branches\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property int $company_id
 * @property string $branch_code
 * @property string $branch_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $address2
 * @property string|null $city
 * @property string|null $country
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $manager_name
 * @property string|null $manager_phone
 * @property string|null $description
 * @property bool $is_main
 * @property int $status
 */
class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermissionTo('edit-branches');
    }

    public function rules(): array
    {
        $branchId = (int) $this->route('branch');
        $companyId = $this->input('company_id', 0);

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_code' => [
                'required', 'string', 'max:50',
                Rule::unique('branches', 'branch_code')
                    ->ignore($branchId)
                    ->where('company_id', $companyId),
            ],
            'branch_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:10'],
            'state' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'manager_phone' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'is_main' => ['boolean'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => __('branches.company_id_required'),
            'company_id.exists' => __('branches.company_id_exists'),
            'branch_code.required' => __('branches.branch_code_required'),
            'branch_code.unique' => __('branches.branch_code_unique'),
            'branch_name.required' => __('branches.branch_name_required'),
            'email.email' => __('branches.email_invalid'),
            'status.required' => __('branches.status_required'),
            'status.in' => __('branches.status_invalid'),
        ];
    }
}
