<?php

namespace Modules\Subordinations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubordinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasPermissionTo('edit-subordinations');
    }

    public function rules(): array
    {
        $subordinationId = (int) $this->route('subordination');

        return [
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('subordinations', 'code')->ignore($subordinationId),
            ],
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => __('subordinations.code_required'),
            'code.regex' => __('subordinations.code_regex'),
            'code.max' => __('subordinations.code_max'),
            'code.unique' => __('subordinations.code_unique'),
            'name_ar.required' => __('subordinations.name_ar_required'),
            'name_ar.max' => __('subordinations.name_ar_max'),
            'name_en.max' => __('subordinations.name_en_max'),
            'status.in' => __('subordinations.status_in'),
            'sort_order.integer' => __('subordinations.sort_order_integer'),
            'sort_order.min' => __('subordinations.sort_order_min'),
        ];
    }
}
