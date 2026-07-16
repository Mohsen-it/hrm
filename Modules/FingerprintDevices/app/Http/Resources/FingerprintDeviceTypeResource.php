<?php

namespace Modules\FingerprintDevices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;

/**
 * @mixin FingerprintDeviceType
 */
class FingerprintDeviceTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'manufacturer' => $this->manufacturer,
            'protocol' => $this->protocol,
            'sdk_version' => $this->sdk_version,
            'default_port' => $this->default_port,
            'supports_fingerprint' => (bool) $this->supports_fingerprint,
            'supports_face' => (bool) $this->supports_face,
            'max_fingerprints' => $this->max_fingerprints,
            'max_users' => $this->max_users,
            'connection_params' => $this->connection_params,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'devices_count' => $this->whenCounted('devices'),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
