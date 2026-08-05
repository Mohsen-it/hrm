<?php

namespace Modules\Vacations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Vacations\Models\AttendanceJustificationRequest;

/** Stable frontend representation that never exposes UTC timestamps for date-only fields. */
class AttendanceJustificationRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AttendanceJustificationRequest $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'user_id' => $item->user_id,
            'user' => $item->user ? ['id' => $item->user->id, 'name' => $item->user->name, 'employee_code' => $item->user->employee_code] : null,
            'attendance_date' => $item->attendance_date?->format('Y-m-d'),
            'arrival_time' => $item->arrival_time ? substr((string) $item->arrival_time, 0, 5) : null,
            'missing_check_in' => (bool) $item->missing_check_in,
            'missing_check_out' => (bool) $item->missing_check_out,
            'late_arrival' => (bool) $item->late_arrival,
            'late_minutes' => (int) $item->late_minutes,
            'reason' => $item->reason,
        ];
    }
}
