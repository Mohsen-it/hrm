<?php

namespace Modules\Companies\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
            'company_code' => $this->company_code,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'address2' => $this->address2,
            'city' => $this->city,
            'country' => $this->country,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'website' => $this->website,
            'logo' => $this->logo,
            'logo_url' => $this->logo_url,
            'description' => $this->description,
            'established_date' => $this->established_date?->format('Y-m-d'),
            'tax_number' => $this->tax_number,
            'commercial_number' => $this->commercial_number,
            'is_default' => $this->is_default,
            'status' => $this->status,
            'branches' => $this->whenLoaded('branches'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
