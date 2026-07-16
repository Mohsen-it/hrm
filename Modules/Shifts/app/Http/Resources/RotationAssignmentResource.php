<?php

namespace Modules\Shifts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RotationAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'rotation_id' => $this->rotation_id,
            'rotation_group_id' => $this->rotation_group_id,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee->id,
                    'name' => $this->employee->name,
                    'first_name' => $this->employee->first_name,
                    'last_name' => $this->employee->last_name,
                    'employee_code' => $this->employee->employee_code,
                    'department_id' => $this->employee->department_id,
                    'department_name' => $this->employee->department?->department_name,
                ];
            }),
            'rotation' => $this->whenLoaded('rotation', function () {
                return [
                    'id' => $this->rotation->id,
                    'name' => $this->rotation->name,
                    'cycle_length' => $this->rotation->cycle_length,
                    'color' => $this->rotation->color,
                ];
            }),
            'rotation_group' => $this->whenLoaded('rotationGroup', function () {
                return [
                    'id' => $this->rotationGroup->id,
                    'name' => $this->rotationGroup->name,
                    'group_index' => $this->rotationGroup->group_index,
                ];
            }),
            'status' => $this->end_date ? 'closed' : 'active',
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
