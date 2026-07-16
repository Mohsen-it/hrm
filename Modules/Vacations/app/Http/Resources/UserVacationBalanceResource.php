<?php

namespace Modules\Vacations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Vacations\Models\UserVacationBalance;

/**
 * UserVacationBalanceResource — wire-format for a single balance row.
 */
class UserVacationBalanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var UserVacationBalance $balance */
        $balance = $this->resource;

        return [
            'id' => $balance->id,
            'user_id' => $balance->user_id,
            'user' => $balance->user ? [
                'id' => $balance->user->id,
                'name' => $balance->user->name,
                'employee_code' => $balance->user->employee_code,
            ] : null,
            'vacation_type_id' => $balance->vacation_type_id,
            'vacation_type' => $balance->vacationType ? [
                'id' => $balance->vacationType->id,
                'code' => $balance->vacationType->code,
                'name_ar' => $balance->vacationType->name_ar,
                'name_en' => $balance->vacationType->name_en,
                'color' => $balance->vacationType->color,
            ] : null,
            'year' => (int) $balance->year,
            'days_entitled' => (int) $balance->days_entitled,
            'total_days' => (int) $balance->days_entitled,
            'days_used' => (int) $balance->days_used,
            'days_pending' => (int) $balance->days_pending,
            'days_carried_over' => (int) $balance->days_carried_over,
            'days_adjustment' => (int) $balance->days_adjustment,
            'days_remaining' => $balance->daysRemaining(),
            'remaining_days' => $balance->daysRemaining(),
            'period_start' => $balance->period_start?->format('Y-m-d'),
            'period_end' => $balance->period_end?->format('Y-m-d'),
            'last_recalculated_at' => $balance->last_recalculated_at?->format('Y-m-d H:i'),
            'notes' => $balance->notes,
            'created_at' => $balance->created_at?->format('Y-m-d H:i'),
            'updated_at' => $balance->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
