<?php

namespace Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'employee_code' => $this->employee_code,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'full_name_ar' => $this->full_name_ar,
            'full_name_en' => $this->full_name_en,
            'email' => $this->email,
            'national_id' => $this->national_id,
            'phone' => $this->phone,
            'phone2' => $this->phone2,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'marital_status' => $this->marital_status,
            'nationality' => $this->nationality,

            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'termination_date' => $this->termination_date?->format('Y-m-d'),
            'employment_type' => $this->employment_type,
            'job_title' => $this->job_title,
            'work_location' => $this->work_location,

            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,

            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,

            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'iban' => $this->iban,

            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar_url,

            'status' => $this->status,
            'is_active_employee' => $this->is_active_employee,
            'is_active' => $this->isActive(),
            'is_locked' => $this->isLocked(),
            'is_super_admin' => $this->isSuperAdmin(),
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'last_login_ip' => $this->last_login_ip,
            'must_change_password' => $this->must_change_password,

            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'grade_id' => $this->grade_id,
            'subordination_id' => $this->subordination_id,
            'shift_id' => $this->shift_id,
            'manager_id' => $this->manager_id,

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
            'position' => $this->whenLoaded('position', function () {
                return $this->position ? [
                    'id' => $this->position->id,
                    'position_name' => $this->position->position_name,
                ] : null;
            }),
            'grade' => $this->whenLoaded('grade', function () {
                return $this->grade ? [
                    'id' => $this->grade->id,
                    'grade_name' => $this->grade->grade_name,
                ] : null;
            }),
            'subordination' => $this->whenLoaded('subordination', function () {
                return $this->subordination ? [
                    'id' => $this->subordination->id,
                    'code' => $this->subordination->code,
                    'name_ar' => $this->subordination->name_ar,
                    'name_en' => $this->subordination->name_en,
                    'display_name' => $this->subordination->display_name,
                ] : null;
            }),
            'shift' => $this->whenLoaded('shift', function () {
                return $this->shift ? [
                    'id' => $this->shift->id,
                    'shift_name' => $this->shift->shift_name,
                ] : null;
            }),
            'manager' => $this->whenLoaded('manager', function () {
                return $this->manager ? [
                    'id' => $this->manager->id,
                    'name' => $this->manager->name,
                ] : null;
            }),
            'shifts' => $this->whenLoaded('shifts', function () {
                return $this->shifts->map(fn ($shift) => [
                    'id' => $shift->id,
                    'shift_name' => $shift->shift_name,
                    'shift_code' => $shift->shift_code,
                    'pivot' => [
                        'effective_from' => $shift->pivot->effective_from,
                        'effective_to' => $shift->pivot->effective_to,
                        'is_primary' => (bool) $shift->pivot->is_primary,
                    ],
                ]);
            }),
            'zones' => $this->whenLoaded('zones'),
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                ]);
            }),
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->pluck('name');
            }),
            'all_permissions' => $this->when(
                $this->relationLoaded('roles') || $this->relationLoaded('permissions'),
                fn () => $this->getAllPermissions()->pluck('name')
            ),
            'subordinates' => $this->whenLoaded('subordinates'),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
