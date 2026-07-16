<?php

namespace Modules\Positions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
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
            'department_id' => $this->department_id,
            'position_code' => $this->position_code,
            'position_name' => $this->position_name,
            'description' => $this->description,
            'min_salary' => $this->min_salary,
            'max_salary' => $this->max_salary,
            'requirements' => $this->requirements,
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
            'department' => $this->whenLoaded('department', function () {
                return $this->department ? [
                    'id' => $this->department->id,
                    'department_name' => $this->department->department_name,
                ] : null;
            }),
            'users' => $this->whenLoaded('users'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
