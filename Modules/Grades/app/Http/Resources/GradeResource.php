<?php

namespace Modules\Grades\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
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
            'grade_code' => $this->grade_code,
            'grade_name' => $this->grade_name,
            'level' => $this->level,
            'min_salary' => $this->min_salary,
            'max_salary' => $this->max_salary,
            'description' => $this->description,
            'status' => $this->status,
            'company' => $this->whenLoaded('company', function () {
                return $this->company ? [
                    'id' => $this->company->id,
                    'company_name' => $this->company->company_name,
                ] : null;
            }),
            'users' => $this->whenLoaded('users'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
