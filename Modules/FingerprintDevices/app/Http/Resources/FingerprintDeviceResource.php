<?php

namespace Modules\FingerprintDevices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FingerprintDevices\Models\FingerprintDevice;

/**
 * @mixin FingerprintDevice
 */
class FingerprintDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_type_id' => $this->device_type_id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'ip_address' => $this->ip_address,
            'port' => $this->port,
            'comm_key' => $this->comm_key,
            'timezone' => $this->timezone,
            'connection_type' => $this->connection_type,
            'timeout' => $this->timeout,
            'status' => $this->status,
            'last_seen_at' => $this->last_seen_at?->toDateTimeString(),
            'last_synced_at' => $this->last_synced_at?->toDateTimeString(),
            'capabilities' => $this->capabilities,
            'user_count' => $this->user_count,
            'fingerprint_count' => $this->fingerprint_count,
            'attendance_log_count' => $this->attendance_log_count,
            'notes' => $this->notes,
            'is_push_enabled' => (bool) $this->is_push_enabled,
            'push_url' => $this->push_url,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'device_type' => $this->whenLoaded('deviceType', fn () => new FingerprintDeviceTypeResource($this->deviceType)),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
                'branch_code' => $this->branch->branch_code,
            ] : null),
        ];
    }
}
