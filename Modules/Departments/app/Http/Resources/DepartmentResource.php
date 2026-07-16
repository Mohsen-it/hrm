<?php

namespace Modules\Departments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'manager_id' => $this->manager_id,
            'department_code' => $this->department_code,
            'department_name' => $this->department_name,
            'description' => $this->description,
            'phone' => $this->phone,
            'email' => $this->email,
            'location' => $this->location,
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
            'manager' => $this->whenLoaded('manager', function () {
                return $this->manager ? [
                    'id' => $this->manager->id,
                    'name' => $this->manager->name,
                ] : null;
            }),
            'parent' => $this->whenLoaded('parent', function () {
                return $this->parent ? [
                    'id' => $this->parent->id,
                    'department_name' => $this->parent->department_name,
                ] : null;
            }),
            'children' => $this->whenLoaded('children'),
            'users' => $this->whenLoaded('users'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
