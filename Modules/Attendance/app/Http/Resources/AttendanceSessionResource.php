<?php

namespace Modules\Attendance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Attendance\Support\TimeFormatter;

/**
 * AttendanceSessionResource — single session row for the UI.
 *
 * Wraps the Eloquent model in a stable shape: dates are normalised, minutes
 * are pre-formatted, and related entities are surfaced as `id` + `name`
 * objects via `whenLoaded` to prevent N+1 payloads.
 */
class AttendanceSessionResource extends JsonResource
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
            'attendance_date' => TimeFormatter::dateOf($this->attendance_date),
            'check_in_at' => TimeFormatter::dateTimeOf($this->check_in_at),
            'check_out_at' => TimeFormatter::dateTimeOf($this->check_out_at),
            'expected_check_in' => TimeFormatter::timeOf($this->expected_check_in),
            'expected_check_out' => TimeFormatter::timeOf($this->expected_check_out),
            'status' => $this->status,
            'session_type' => $this->session_type,
            'source' => $this->source,
            'work_minutes' => (int) $this->work_minutes,
            'break_minutes' => (int) $this->break_minutes,
            'late_minutes' => (int) $this->late_minutes,
            'early_leave_minutes' => (int) $this->early_leave_minutes,
            'overtime_minutes' => (int) $this->overtime_minutes,
            'work_human' => TimeFormatter::minutesToHuman((int) $this->work_minutes),
            'overtime_human' => TimeFormatter::minutesToHuman((int) $this->overtime_minutes),
            'late_human' => TimeFormatter::minutesToHuman((int) $this->late_minutes),
            'is_open' => $this->isOpen(),
            'is_complete' => $this->isComplete(),
            'notes' => $this->notes,
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
            'raw_log' => $this->whenLoaded('rawLog', function () {
                return $this->rawLog ? [
                    'id' => $this->rawLog->id,
                    'punch_time' => TimeFormatter::dateTimeOf($this->rawLog->punch_time),
                    'punch_type' => $this->rawLog->punch_type,
                ] : null;
            }),
            'created_at' => TimeFormatter::dateTimeOf($this->created_at),
            'updated_at' => TimeFormatter::dateTimeOf($this->updated_at),
        ];
    }
}
