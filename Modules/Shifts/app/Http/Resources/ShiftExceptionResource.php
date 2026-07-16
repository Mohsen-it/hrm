<?php

namespace Modules\Shifts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftExceptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->name),
            'exception_type' => $this->exception_type,
            'source' => $this->source,
            'source_id' => $this->source_id,
            'from_date' => $this->from_date?->format('Y-m-d'),
            'to_date' => $this->to_date?->format('Y-m-d'),
            'status' => $this->status,
            'reason' => $this->reason,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
