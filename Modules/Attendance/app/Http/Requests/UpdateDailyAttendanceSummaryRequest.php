<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateDailyAttendanceSummaryRequest — validate a manual patch on a
 * daily summary row (typically the `status`, `session_type`, or `notes`
 * columns; the timing columns are recalculated by the service).
 */
class UpdateDailyAttendanceSummaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = ['present', 'absent', 'late', 'early_leave', 'missing_punch', 'holiday', 'vacation', 'weekend'];
        $types = ['normal', 'overtime', 'make_up'];

        return [
            'status' => ['sometimes', Rule::in($statuses)],
            'session_type' => ['sometimes', Rule::in($types)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
