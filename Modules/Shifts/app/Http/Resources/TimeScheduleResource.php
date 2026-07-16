<?php

namespace Modules\Shifts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeScheduleResource extends JsonResource
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
            'name' => $this->name,
            'in_time' => $this->in_time,
            'out_time' => $this->out_time,
            'is_multi_day' => $this->is_multi_day,
            'late_margin' => $this->late_margin,
            'early_margin' => $this->early_margin,
            'breaks' => $this->whenLoaded('breaks', function () {
                return $this->breaks->map(function ($break) {
                    return [
                        'id' => $break->id,
                        'break_start' => $break->break_start,
                        'duration' => $break->duration,
                        'break_end' => $break->break_end,
                    ];
                });
            }),
            'linked_category_name' => $this->whenLoaded('categoryTimeSchedule.shiftCategory', function () {
                return $this->categoryTimeSchedule?->shiftCategory?->name;
            }),
            'linked_category_id' => $this->whenLoaded('categoryTimeSchedule.shiftCategory', function () {
                return $this->categoryTimeSchedule?->shiftCategory?->id;
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
