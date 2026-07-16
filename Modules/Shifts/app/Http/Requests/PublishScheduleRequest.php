<?php

namespace Modules\Shifts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_id' => ['required', 'integer', 'exists:schedule_periods,id'],
        ];
    }
}
