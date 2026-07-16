<?php

namespace Modules\Shifts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleCalendarResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->date ?? null,
            'day_name' => $this->day_name ?? null,
            'day_of_week' => $this->day_of_week ?? null,
            'status' => $this->status ?? 'rest',
            'is_work_day' => $this->is_work_day ?? false,
            'in_time' => $this->in_time ?? null,
            'out_time' => $this->out_time ?? null,
        ];
    }
}
