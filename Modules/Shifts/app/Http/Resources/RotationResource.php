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
            'time_schedule_id' => $this->time_schedule_id,
            'overtime_enabled' => $this->overtime_enabled,
            'work_on_holidays' => $this->work_on_holidays,
            'grace_minutes' => $this->grace_minutes,
            'color' => $this->color,
            'time_schedule' => $this->whenLoaded('timeSchedule', function () {
                return $this->timeSchedule ? [
                    'id' => $this->timeSchedule->id,
                    'name' => $this->timeSchedule->name,
                    'in_time' => $this->timeSchedule->in_time,
                    'out_time' => $this->timeSchedule->out_time,
                    'is_multi_day' => $this->timeSchedule->is_multi_day,
                    'late_margin' => $this->timeSchedule->late_margin,
                    'early_margin' => $this->timeSchedule->early_margin,
                    'in_ahead_margin' => $this->timeSchedule->in_ahead_margin,
                    'in_above_margin' => $this->timeSchedule->in_above_margin,
                    'out_ahead_margin' => $this->timeSchedule->out_ahead_margin,
                    'out_above_margin' => $this->timeSchedule->out_above_margin,
                ] : null;
            }),
            'active_employees_count' => $this->when(isset($this->active_employees_count), fn () => $this->active_employees_count),
            'groups' => $this->whenLoaded('groups', function () use ($request) {
                return $this->groups->map(fn ($g) => (new RotationGroupResource($g))->resolve($request))->values()->all();
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
