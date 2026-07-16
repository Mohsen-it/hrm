<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateAttendanceSessionRequest — validate a partial update on an existing
 * session. Only the operator-editable columns are exposed; the
 * timing-related columns (work_minutes, late_minutes, …) are recomputed by
 * the service when the check-in / check-out timestamps change.
 */
class UpdateAttendanceSessionRequest extends FormRequest
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
        $types = ['normal', 'overtime', 'make_up'];
        $sources = ['device', 'manual', 'api', 'adms', 'bio'];

        return [
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'attendance_date' => ['sometimes', 'date_format:Y-m-d'],
            'check_in_at' => ['sometimes', 'date'],
            'check_out_at' => ['nullable', 'date'],
            'session_type' => ['sometimes', Rule::in($types)],
            'source' => ['sometimes', Rule::in($sources)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
