<?php

namespace Modules\Vacations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Vacations\Models\VacationType;

/**
 * VacationTypeResource — wire-format for a single vacation type.
 *
 * Surfaces both Arabic and English names plus the machine code so the
 * frontend can render localized chips and trigger the matching balance
 * flows without an extra round-trip.
 */
class VacationTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var VacationType $type */
        $type = $this->resource;

        return [
            'id' => $type->id,
            'code' => $type->code,
            'name_ar' => $type->name_ar,
            'name_en' => $type->name_en,
            'name' => $type->name_ar,
            'color' => $type->color,
            'icon' => $type->icon,
            'default_days_per_year' => (int) $type->default_days_per_year,
            'max_days_per_request' => (int) $type->max_days_per_request,
            'max_carry_days' => (int) $type->max_carry_days,
            'advance_notice_days' => (int) $type->advance_notice_days,
            'is_paid' => (bool) $type->is_paid,
            'requires_approval' => (bool) $type->requires_approval,
            'requires_attachment' => (bool) $type->requires_attachment,
            'deducts_from_balance' => (bool) $type->deducts_from_balance,
            'counts_weekends' => (bool) $type->counts_weekends,
            'counts_holidays' => (bool) $type->counts_holidays,
            'is_active' => (bool) $type->is_active,
            'sort_order' => (int) $type->sort_order,
            'description' => $type->description,
            'created_at' => $type->created_at?->format('Y-m-d H:i'),
            'updated_at' => $type->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
