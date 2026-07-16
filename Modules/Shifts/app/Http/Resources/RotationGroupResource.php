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
            'time_schedule_id' => $this->time_schedule_id,
            'time_schedule' => $this->whenLoaded('timeSchedule', function () {
                return $this->timeSchedule ? [
                    'id' => $this->timeSchedule->id,
                    'name' => $this->timeSchedule->name,
                    'in_time' => $this->timeSchedule->in_time,
                    'out_time' => $this->timeSchedule->out_time,
                ] : null;
            }),
            'active_employees_count' => $this->when(isset($this->active_employees_count), fn () => $this->active_employees_count),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
