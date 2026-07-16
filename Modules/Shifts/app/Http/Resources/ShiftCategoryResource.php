<?php

namespace Modules\Shifts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftCategoryResource extends JsonResource
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
            'type' => $this->type,
            'work_days' => $this->work_days,
            'rest_days' => $this->rest_days,
            'work_days_json' => $this->work_days_json,
            'weekend_days_json' => $this->weekend_days_json,
            'required_hours' => $this->required_hours,
            'period_type' => $this->period_type,
            'overtime_enabled' => $this->overtime_enabled,
            'fingerprint_enabled' => $this->fingerprint_enabled,
            'work_on_holidays' => $this->work_on_holidays,
            'work_on_weekends' => $this->work_on_weekends,
            'color' => $this->color,
            'anchor_start_date' => $this->anchor_start_date?->format('Y-m-d'),
            'cycle_length' => $this->cycle_length,
            'is_dynamic' => $this->is_dynamic,
            'time_schedule_id' => $this->whenLoaded('categoryTimeSchedule.timeSchedule', function () {
                return $this->categoryTimeSchedule?->timeSchedule?->id;
            }),
            'time_schedule' => $this->whenLoaded('timeSchedule', function () {
                return $this->timeSchedule ? [
                    'id' => $this->timeSchedule->id,
                    'name' => $this->timeSchedule->name,
                    'in_time' => $this->timeSchedule->in_time,
                    'out_time' => $this->timeSchedule->out_time,
                    'is_multi_day' => $this->timeSchedule->is_multi_day,
                    'late_margin' => $this->timeSchedule->late_margin,
                    'early_margin' => $this->timeSchedule->early_margin,
                ] : null;
            }),
            'active_employees_count' => $this->whenCounted('employees'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
