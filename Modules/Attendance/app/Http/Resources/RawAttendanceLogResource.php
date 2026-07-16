<?php

namespace Modules\Attendance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Attendance\Support\TimeFormatter;

/**
 * RawAttendanceLogResource — single raw log row for the UI.
 */
class RawAttendanceLogResource extends JsonResource
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
            'device_id' => $this->device_id,
            'device_user_id' => $this->device_user_id,
            'punch_time' => TimeFormatter::dateTimeOf($this->punch_time),
            'punch_type' => $this->punch_type,
            'verify_type' => $this->verify_type,
            'work_code' => (int) $this->work_code,
            'source' => $this->source,
            'processed' => (bool) $this->processed,
            'processed_at' => TimeFormatter::dateTimeOf($this->processed_at),
            'ip_address' => $this->ip_address,
            'raw_data' => $this->raw_data,
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'employee_code' => $this->user->employee_code,
                ] : null;
            }),
            'created_at' => TimeFormatter::dateTimeOf($this->created_at),
            'updated_at' => TimeFormatter::dateTimeOf($this->updated_at),
        ];
    }
}
