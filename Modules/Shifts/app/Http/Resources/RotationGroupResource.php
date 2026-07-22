<?php

namespace Modules\Shifts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RotationGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rotation_id' => $this->rotation_id,
            'name' => $this->name,
            'group_index' => $this->group_index,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'rotation' => $this->whenLoaded('rotation', function () {
                return [
                    'id' => $this->rotation->id,
                    'name' => $this->rotation->name,
                ];
            }),
            'active_employees_count' => $this->when(isset($this->active_employees_count), fn () => $this->active_employees_count),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
