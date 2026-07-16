<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreAttendanceSessionRequest — validate a manual check-in payload.
 *
 * Used by the operator when creating a session from the UI (the device
 * path does not go through this request). Only the fields the operator is
 * allowed to set are validated here — derived columns (work_minutes,
 * status, late_minutes) are filled in by the service.
 */
class StoreAttendanceSessionRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'check_in_at' => ['required', 'date'],
            'check_out_at' => ['nullable', 'date', 'after_or_equal:check_in_at'],
            'session_type' => ['nullable', Rule::in($types)],
            'source' => ['nullable', Rule::in($sources)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
