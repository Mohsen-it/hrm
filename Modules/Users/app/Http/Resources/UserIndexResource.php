<?php

namespace Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserIndexResource extends JsonResource
{
    /**
     * Transform the resource into a lightweight array for the index page.
     * Only includes fields displayed in the users table.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code,
            'name' => $this->full_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'avatar_url' => $this->avatar_url,
            'email' => $this->email,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'subordination_id' => $this->subordination_id,
            'shift_id' => $this->shift_id,
            'status' => $this->status,

            'company' => $this->whenLoaded('company', fn () => $this->company ? [
                'id' => $this->company->id,
                'company_name' => $this->company->company_name,
            ] : null),

            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
            ] : null),

            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'department_name' => $this->department->department_name,
            ] : null),

            'subordination' => $this->whenLoaded('subordination', fn () => $this->subordination ? [
                'id' => $this->subordination->id,
                'code' => $this->subordination->code,
                'name_ar' => $this->subordination->name_ar,
                'name_en' => $this->subordination->name_en,
                'display_name' => $this->subordination->display_name,
            ] : null),

            'shift' => $this->whenLoaded('shift', fn () => $this->shift ? [
                'id' => $this->shift->id,
                'shift_name' => $this->shift->shift_name,
            ] : null),
        ];
    }
}
