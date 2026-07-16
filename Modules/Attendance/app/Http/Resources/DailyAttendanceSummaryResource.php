<?php

namespace Modules\Attendance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Attendance\Support\TimeFormatter;

/**
 * DailyAttendanceSummaryResource — single daily-summary row for the UI.
 */
class DailyAttendanceSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'shift_id' => $this->shift_id,
            'summary_date' => TimeFormatter::dateOf($this->summary_date),
            'status' => $this->status,
            'session_type' => $this->session_type,
            'first_check_in_at' => TimeFormatter::dateTimeOf($this->first_check_in_at),
            'last_check_out_at' => TimeFormatter::dateTimeOf($this->last_check_out_at),
            'expected_check_in' => TimeFormatter::timeOf($this->expected_check_in),
            'expected_check_out' => TimeFormatter::timeOf($this->expected_check_out),
            'sessions_count' => (int) $this->sessions_count,
            'is_first_punch' => (bool) $this->is_first_punch,
            'is_complete' => (bool) $this->is_complete,
            'total_work_minutes' => (int) $this->total_work_minutes,
            'total_break_minutes' => (int) $this->total_break_minutes,
            'total_overtime_minutes' => (int) $this->total_overtime_minutes,
            'late_minutes' => (int) $this->late_minutes,
            'early_leave_minutes' => (int) $this->early_leave_minutes,
            'work_human' => TimeFormatter::minutesToHuman((int) $this->total_work_minutes),
            'overtime_human' => TimeFormatter::minutesToHuman((int) $this->total_overtime_minutes),
            'late_human' => TimeFormatter::minutesToHuman((int) $this->late_minutes),
            'notes' => $this->notes,
            'calculated_at' => TimeFormatter::dateTimeOf($this->calculated_at),
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'employee_code' => $this->user->employee_code,
                ] : null;
            }),
            'shift' => $this->whenLoaded('shift', function () {
                return $this->shift ? [
                    'id' => $this->shift->id,
                    'shift_name' => $this->shift->shift_name,
                    'shift_code' => $this->shift->shift_code,
                ] : null;
            }),
            'created_at' => TimeFormatter::dateTimeOf($this->created_at),
            'updated_at' => TimeFormatter::dateTimeOf($this->updated_at),
        ];
    }
}
