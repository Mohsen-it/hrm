<?php

namespace Modules\Branches\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
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
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company'),
            'branch_code' => $this->branch_code,
            'branch_name' => $this->branch_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'address2' => $this->address2,
            'city' => $this->city,
            'country' => $this->country,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'manager_name' => $this->manager_name,
            'manager_phone' => $this->manager_phone,
            'description' => $this->description,
            'is_main' => $this->is_main,
            'status' => $this->status,
            'departments' => $this->whenLoaded('departments'),
            'zones' => $this->whenLoaded('zones'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
