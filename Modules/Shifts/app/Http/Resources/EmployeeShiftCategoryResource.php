<?php

namespace Modules\Shifts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeShiftCategoryResource extends JsonResource
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
            'employee' => $this->whenLoaded('employee', function () {
                return $this->employee ? [
                    'id' => $this->employee->id,
                    'first_name' => $this->employee->first_name,
                    'last_name' => $this->employee->last_name,
                    'emp_code' => $this->employee->employee_code,
                ] : null;
            }),
            'category' => $this->whenLoaded('shiftCategory', function () {
                return $this->shiftCategory ? [
                    'id' => $this->shiftCategory->id,
                    'name' => $this->shiftCategory->name,
                    'type' => $this->shiftCategory->type,
                ] : null;
            }),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'is_active' => $this->end_date === null,
            'snapshot_data' => $this->snapshot_data,
        ];
    }
}
