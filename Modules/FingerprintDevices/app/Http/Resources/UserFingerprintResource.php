<?php

namespace Modules\FingerprintDevices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FingerprintDevices\Models\UserFingerprint;

/**
 * @mixin UserFingerprint
 */
class UserFingerprintResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'device_id' => $this->device_id,
            'finger_id' => $this->finger_id,
            'template_data' => $this->template_data,
            'template_format' => $this->template_format,
            'template_version' => $this->template_version,
            'quality' => $this->quality,
            'is_master' => (bool) $this->is_master,
            'captured_at' => $this->captured_at?->toDateTimeString(),
            'synced_at' => $this->synced_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'device' => $this->whenLoaded('device', fn () => $this->device ? [
                'id' => $this->device->id,
                'name' => $this->device->name,
                'serial_number' => $this->device->serial_number,
                'ip_address' => $this->device->ip_address,
            ] : null),
        ];
    }
}
