<?php

namespace Modules\Zones\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Zones\Models\Zone;

/**
 * ZoneResource — present a Zone for the frontend.
 *
 * @mixin Zone
 */
class ZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company'),
            'code' => $this->code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'display_name' => $this->display_name,
            'zone_type' => $this->zone_type?->value ?? $this->zone_type,
            'zone_type_label' => $this->zone_type?->value
                ? __('zones.zone_type_'.$this->zone_type->value)
                : null,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_meters' => $this->radius_meters,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'branches_count' => (int) ($this->branches_count ?? ($this->branches_count_cache ?? 0)),
            'employees_count' => (int) ($this->employees_count ?? 0),
            'devices_count' => (int) ($this->devices_count ?? 0),
            'branches' => $this->whenLoaded('branches'),
            'branches_count_loaded' => $this->whenCounted('branches'),
            'users' => $this->whenLoaded('users'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
