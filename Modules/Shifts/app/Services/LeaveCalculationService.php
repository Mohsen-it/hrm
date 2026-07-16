<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Modules\Shifts\Models\EmployeeShiftCategory;
use Modules\Shifts\Repositories\ScheduleEntryRepository;

class LeaveCalculationService
{
    public function __construct(
        private CyclicScheduleCalculator $calculator,
        private ScheduleEntryRepository $entryRepository,
    ) {}

    /**
     * Calculate leave days for a given employee and date range.
     *
     * @return array{calendar_days: int, scheduled_work_days: int, scheduled_rest_days: int}
     */
    public function calculateLeaveDays(int $employeeId, Carbon $startDate, Carbon $endDate): array
    {
        $calendarDays = $startDate->diffInDays($endDate) + 1;

        $workDays = $this->entryRepository->countWorkDays($employeeId, $startDate, $endDate);
        $restDays = $calendarDays - $workDays;

        return [
            'calendar_days' => $calendarDays,
            'scheduled_work_days' => $workDays,
            'scheduled_rest_days' => $restDays,
        ];
    }

    /**
     * Calculate leave days using cyclic calculator (fallback when no stored schedule).
     *
     * @return array{calendar_days: int, scheduled_work_days: int, scheduled_rest_days: int}
     */
    public function calculateLeaveDaysFromCycle(
        int $employeeId,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $calendarDays = $startDate->diffInDays($endDate) + 1;

        $assignment = EmployeeShiftCategory::query()
            ->active()
            ->forEmployee($employeeId)
            ->with('shiftCategory')
            ->first();

        if (! $assignment || $assignment->shiftCategory->type !== 'cyclic') {
            return [
                'calendar_days' => $calendarDays,
                'scheduled_work_days' => $calendarDays,
                'scheduled_rest_days' => 0,
            ];
        }

        $category = $assignment->shiftCategory;
        $anchor = $category->cycleAnchor();
        if (! $anchor) {
            return [
                'calendar_days' => $calendarDays,
                'scheduled_work_days' => $calendarDays,
                'scheduled_rest_days' => 0,
            ];
        }

        $workDays = (int) $category->work_days;
        $restDays = (int) $category->rest_days;

        $workCount = 0;
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            if ($this->calculator->isWorkDay($current, $anchor, $workDays, $restDays)) {
                $workCount++;
            }
            $current->addDay();
        }

        return [
            'calendar_days' => $calendarDays,
            'scheduled_work_days' => $workCount,
            'scheduled_rest_days' => $calendarDays - $workCount,
        ];
    }
}
