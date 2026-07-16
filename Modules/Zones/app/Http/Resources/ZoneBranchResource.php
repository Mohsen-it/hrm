<?php

namespace Modules\Zones\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Branches\Models\Branch;

/**
 * ZoneBranchResource — present a Branch within the zone-management UI.
 *
 * The pivot columns are surfaced as `pivot_*` so the frontend can render
 * primary markers and priority without re-querying.
 *
 * @mixin Branch
 */
class ZoneBranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_code' => $this->branch_code,
            'branch_name' => $this->branch_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'state' => $this->state,
            'is_main' => (bool) $this->is_main,
            'status' => $this->status,
            'pivot_is_primary' => (bool) ($this->pivot_is_primary ?? false),
            'pivot_priority' => (int) ($this->pivot_priority ?? 0),
            'pivot_notes' => $this->pivot_notes,
            'is_active' => ((int) $this->status) === 1,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
