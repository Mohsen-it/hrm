<?php

namespace Modules\Shifts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
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
            'branch_id' => $this->branch_id,
            'shift_code' => $this->shift_code,
            'shift_name' => $this->shift_name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'break_minutes' => $this->break_minutes,
            'grace_minutes' => $this->grace_minutes,
            'working_hours' => $this->working_hours,
            'work_days' => $this->work_days,
            'description' => $this->description,
            'status' => $this->status,
            'company' => $this->whenLoaded('company', function () {
                return $this->company ? [
                    'id' => $this->company->id,
                    'company_name' => $this->company->company_name,
                ] : null;
            }),
            'branch' => $this->whenLoaded('branch', function () {
                return $this->branch ? [
                    'id' => $this->branch->id,
                    'branch_name' => $this->branch->branch_name,
                ] : null;
            }),
            'users' => $this->whenLoaded('users'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
