<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Modules\Shifts\Repositories\RotationAssignmentRepository;

class LeaveCalculationService
{
    public function __construct(
        private RotationEngine $rotationEngine,
        private RotationAssignmentRepository $rotationAssignmentRepository,
    ) {}

    /**
     * Calculate leave days for a given employee and date range.
     *
     * Uses rotation engine if employee has a rotation assignment,
     * otherwise falls back to calendar days.
     *
     * @return array{calendar_days: int, scheduled_work_days: int, scheduled_rest_days: int}
     */
    public function calculateLeaveDays(int $employeeId, Carbon $startDate, Carbon $endDate): array
    {
        $calendarDays = $startDate->diffInDays($endDate) + 1;

        $rotationAssignment = $this->rotationAssignmentRepository->getActiveAssignment($employeeId);

        if ($rotationAssignment) {
            $rotation = $rotationAssignment->rotation;
            $group = $rotationAssignment->rotationGroup;
            $workDays = $this->rotationEngine->getWorkDaysInRange($rotation, $group->group_index, $startDate, $endDate);

            return [
                'calendar_days' => $calendarDays,
                'scheduled_work_days' => count($workDays),
                'scheduled_rest_days' => $calendarDays - count($workDays),
            ];
        }

        // Fallback: assume every day is a work day
        return [
            'calendar_days' => $calendarDays,
            'scheduled_work_days' => $calendarDays,
            'scheduled_rest_days' => 0,
        ];
    }
}
