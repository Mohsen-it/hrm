<?php

namespace Modules\Vacations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Vacations\Models\UserVacationRequest;

/**
 * UserVacationRequestResource — wire-format for a single vacation request.
 *
 * Surfaces the user / manager / type / balance names so the frontend
 * can render request lists without an extra round-trip.
 */
class UserVacationRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var UserVacationRequest $vacation */
        $vacation = $this->resource;

        return [
            'id' => $vacation->id,
            'user_id' => $vacation->user_id,
            'user' => $vacation->user ? [
                'id' => $vacation->user->id,
                'name' => $vacation->user->name,
                'employee_code' => $vacation->user->employee_code,
            ] : null,
            'vacation_type_id' => $vacation->vacation_type_id,
            'vacation_type' => $vacation->vacationType ? [
                'id' => $vacation->vacationType->id,
                'code' => $vacation->vacationType->code,
                'name_ar' => $vacation->vacationType->name_ar,
                'name_en' => $vacation->vacationType->name_en,
                'color' => $vacation->vacationType->color,
                'is_paid' => (bool) $vacation->vacationType->is_paid,
            ] : null,
            'manager_id' => $vacation->manager_id,
            'manager' => $vacation->manager ? [
                'id' => $vacation->manager->id,
                'name' => $vacation->manager->name,
            ] : null,
            'balance_id' => $vacation->balance_id,
            'start_date' => $vacation->start_date?->format('Y-m-d'),
            'end_date' => $vacation->end_date?->format('Y-m-d'),
            'days_count' => (int) $vacation->days_count,
            'total_days' => (int) $vacation->days_count,
            'working_days_count' => (int) $vacation->working_days_count,
            'status' => $vacation->status,
            'reason' => $vacation->reason,
            'manager_note' => $vacation->manager_note,
            'requested_at' => $vacation->requested_at?->format('Y-m-d H:i'),
            'decided_at' => $vacation->decided_at?->format('Y-m-d H:i'),
            'cancelled_at' => $vacation->cancelled_at?->format('Y-m-d H:i'),
            'attachments' => $vacation->attachments ?? [],
            'metadata' => $vacation->metadata ?? [],
            'created_at' => $vacation->created_at?->format('Y-m-d H:i'),
            'updated_at' => $vacation->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
