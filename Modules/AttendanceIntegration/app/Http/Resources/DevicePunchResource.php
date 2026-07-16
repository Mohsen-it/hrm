<?php

namespace Modules\AttendanceIntegration\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevicePunchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_user_id' => $this->device_user_id,
            'punch_time' => $this->punch_time,
            'punch_type' => $this->punch_type,
            'verify_type' => $this->verify_type,
            'source' => $this->source,
            'processed' => $this->processed,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'employee_code' => $this->user->employee_code,
            ]),
            'device' => $this->whenLoaded('device', fn () => [
                'id' => $this->device->id,
                'name' => $this->device->name,
                'serial_number' => $this->device->serial_number,
            ]),
        ];
    }
}
