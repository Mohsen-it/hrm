<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;

/**
 * RotationEngine — the core calculation engine for rotation schedules.
 *
 * Given a rotation definition, a group offset, and a target date,
 * it determines whether the employee should work or rest using
 * closed-form date math (no loops, no database queries).
 *
 * Formula:
 *   position_in_cycle = ((target_date - anchor_start_date) + group_offset) % cycle_length
 *   is_work_day = pattern[position_in_cycle] == 1
 */
class RotationEngine
{
    /**
     * Determine if a date is a work day for a given rotation and group.
     */
    public function isWorkDay(Rotation $rotation, int $groupIndex, Carbon|string $targetDate): bool
    {
        $date = Carbon::parse($targetDate)->startOfDay();
        $anchor = $rotation->anchor_start_date->startOfDay();
        $pattern = $rotation->pattern;

        if (! is_array($pattern) || $rotation->cycle_length <= 0) {
            return false;
        }

        $daysSinceAnchor = (int) $date->diffInDays($anchor);
        $positionInCycle = ($daysSinceAnchor + $groupIndex) % $rotation->cycle_length;

        return ($pattern[$positionInCycle] ?? 0) == 1;
    }

    /**
     * Get the day index within the cycle for a specific date and group.
     *
     * @return int|null 1-based day index, or null if cycle is invalid
     */
    public function dayIndex(Rotation $rotation, int $groupIndex, Carbon|string $targetDate): ?int
    {
        $date = Carbon::parse($targetDate)->startOfDay();
        $anchor = $rotation->anchor_start_date->startOfDay();
        $cycleLength = $rotation->cycle_length;

        if ($cycleLength <= 0) {
            return null;
        }

        $daysSinceAnchor = (int) $date->diffInDays($anchor);
        $positionInCycle = ($daysSinceAnchor + $groupIndex) % $cycleLength;

        return $positionInCycle + 1;
    }

    /**
     * Get the full schedule for a date range for a specific rotation and group.
     *
     * @return array<int, array{date: string, is_work_day: bool, day_index: int}>
     */
    public function getScheduleInRange(Rotation $rotation, int $groupIndex, Carbon|string $fromDate, Carbon|string $toDate): array
    {
        $current = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->startOfDay();
        $schedule = [];

        while ($current->lte($end)) {
            $schedule[] = [
                'date' => $current->format('Y-m-d'),
                'is_work_day' => $this->isWorkDay($rotation, $groupIndex, $current),
                'day_index' => $this->dayIndex($rotation, $groupIndex, $current) ?? 0,
            ];
            $current->addDay();
        }

        return $schedule;
    }

    /**
     * Get work days only in a date range for a specific rotation and group.
     *
     * @return array<int, string>
     */
    public function getWorkDaysInRange(Rotation $rotation, int $groupIndex, Carbon|string $fromDate, Carbon|string $toDate): array
    {
        $schedule = $this->getScheduleInRange($rotation, $groupIndex, $fromDate, $toDate);

        return array_values(array_column(
            array_filter($schedule, fn (array $day): bool => $day['is_work_day']),
            'date'
        ));
    }

    /**
     * Get the next work day from a given date for a specific rotation and group.
     */
    public function getNextWorkDay(Rotation $rotation, int $groupIndex, Carbon|string $fromDate): Carbon
    {
        $next = Carbon::parse($fromDate)->startOfDay()->addDay();

        while (! $this->isWorkDay($rotation, $groupIndex, $next)) {
            $next->addDay();
        }

        return $next;
    }

    /**
     * Get the next rest day from a given date for a specific rotation and group.
     */
    public function getNextRestDay(Rotation $rotation, int $groupIndex, Carbon|string $fromDate): Carbon
    {
        $next = Carbon::parse($fromDate)->startOfDay()->addDay();

        while ($this->isWorkDay($rotation, $groupIndex, $next)) {
            $next->addDay();
        }

        return $next;
    }

    /**
     * Build the standard resolver contract for a rotation employee.
     *
     * @return array{
     *     employee_id: int,
     *     target_date: string,
     *     is_work_day: bool,
     *     status: string,
     *     expected_check_in: ?string,
     *     expected_check_out: ?string,
     *     day_index: ?int,
     *     cycle_length: ?int,
     *     rotation_id: ?int,
     *     rotation_group_id: ?int,
     *     exception_id: ?int,
     *     source: string
     * }
     */
    public function resolve(
        int $employeeId,
        Rotation $rotation,
        RotationGroup $group,
        Carbon|string $targetDate,
        ?string $expectedCheckIn = null,
        ?string $expectedCheckOut = null,
        ?int $exceptionId = null,
    ): array {
        $date = Carbon::parse($targetDate)->startOfDay();

        return [
            'employee_id' => $employeeId,
            'target_date' => $date->toDateString(),
            'is_work_day' => $this->isWorkDay($rotation, $group->group_index, $date),
            'status' => $this->isWorkDay($rotation, $group->group_index, $date)
                ? ScheduleResolverService::STATUS_WORK
                : ScheduleResolverService::STATUS_REST,
            'expected_check_in' => $expectedCheckIn,
            'expected_check_out' => $expectedCheckOut,
            'day_index' => $this->dayIndex($rotation, $group->group_index, $date),
            'cycle_length' => $rotation->cycle_length,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $group->id,
            'exception_id' => $exceptionId,
            'source' => 'rotation',
        ];
    }

    /**
     * Resolve expected check-in/out times from rotation group snapshot or time schedule.
     */
    public function resolveTimes(RotationAssignment $assignment): array
    {
        $snapshot = $assignment->snapshot_data;

        $expectedIn = $snapshot['time_schedule']['in_time'] ?? null;
        $expectedOut = $snapshot['time_schedule']['out_time'] ?? null;

        if ($expectedIn && $expectedOut) {
            return [
                'check_in' => substr((string) $expectedIn, 0, 5),
                'check_out' => substr((string) $expectedOut, 0, 5),
            ];
        }

        $timeSchedule = $assignment->rotationGroup?->timeSchedule;

        if ($timeSchedule) {
            return [
                'check_in' => $timeSchedule->in_time ? $timeSchedule->in_time->format('H:i') : null,
                'check_out' => $timeSchedule->out_time ? $timeSchedule->out_time->format('H:i') : null,
            ];
        }

        return ['check_in' => null, 'check_out' => null];
    }
}
