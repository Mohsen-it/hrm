<?php

namespace Modules\Companies\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * @property string $company_code
 * @property string $company_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $address2
 * @property string|null $city
 * @property string|null $country
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $website
 * @property UploadedFile|null $logo
 * @property string|null $description
 * @property string|null $established_date
 * @property string|null $tax_number
 * @property string|null $commercial_number
 * @property bool $is_default
 * @property int $status
 */
class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermissionTo('create-companies');
    }

    public function rules(): array
    {
        return [
            'company_code' => ['required', 'string', 'max:50', 'unique:companies,company_code'],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:companies,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:10'],
            'state' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'description' => ['nullable', 'string'],
            'established_date' => ['nullable', 'date'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'commercial_number' => ['nullable', 'string', 'max:50'],
            'is_default' => ['boolean'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_code.required' => __('companies.company_code_required'),
            'company_code.unique' => __('companies.company_code_unique'),
            'company_name.required' => __('companies.company_name_required'),
            'email.email' => __('companies.email_invalid'),
            'email.unique' => __('companies.email_unique'),
            'website.url' => __('companies.website_invalid'),
            'logo.image' => __('companies.logo_invalid'),
            'logo.mimes' => __('companies.logo_mimes'),
            'logo.max' => __('companies.logo_max_size'),
            'status.required' => __('companies.status_required'),
            'status.in' => __('companies.status_invalid'),
        ];
    }
}
