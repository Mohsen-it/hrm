<?php

namespace Modules\Shifts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'anchor_start_date' => $this->anchor_start_date?->format('Y-m-d'),
            'pattern' => $this->pattern,
            'cycle_length' => $this->cycle_length,
            'work_days_count' => $this->work_days_count,
            'rest_days_count' => $this->rest_days_count,
            'number_of_groups' => $this->number_of_groups,
            'overtime_enabled' => $this->overtime_enabled,
            'work_on_holidays' => $this->work_on_holidays,
            'grace_minutes' => $this->grace_minutes,
            'color' => $this->color,
            'in_ahead_margin' => $this->in_ahead_margin ? $this->in_ahead_margin->format('H:i') : null,
            'in_above_margin' => $this->in_above_margin ? $this->in_above_margin->format('H:i') : null,
            'out_ahead_margin' => $this->out_ahead_margin ? $this->out_ahead_margin->format('H:i') : null,
            'out_above_margin' => $this->out_above_margin ? $this->out_above_margin->format('H:i') : null,
            'active_employees_count' => $this->when(isset($this->active_employees_count), fn () => $this->active_employees_count),
            'groups' => RotationGroupResource::collection($this->whenLoaded('groups')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
