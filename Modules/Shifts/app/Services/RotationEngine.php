<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;

/**
 * RotationEngine — the core calculation engine for rotation schedules.
 *
 * Given a rotation definition, a group index, and a target date,
 * it determines whether the employee should work or rest using
 * closed-form date math (no loops, no database queries).
 *
 * Formula:
 *   position_in_cycle = ((target_date - anchor_start_date) + (group_index * work_days_count)) % cycle_length
 *   is_work_day = pattern[position_in_cycle] == 1
 *
 * The offset is `group_index * work_days_count` (not just `group_index`) so that
 * each group works a contiguous block of `work_days_count` days and the groups
 * tile the cycle without overlap. This is the only formula that yields
 * continuous coverage when number_of_groups × work_days_count <= cycle_length.
 */
class RotationEngine
{
    /**
     * Resolve the cycle offset for a given group.
     *
     * Each group works a contiguous block of `work_days_count` days, evenly
     * distributed across the cycle by multiplying the group index by the
     * number of work days.
     */
    private function groupOffset(Rotation $rotation): int
    {
        $workDaysCount = $rotation->work_days_count;

        if (! $workDaysCount) {
            $pattern = is_array($rotation->pattern) ? $rotation->pattern : [];
            $workDaysCount = count(array_filter($pattern, fn ($v) => $v == 1));
        }

        return (int) $workDaysCount;
    }

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
        $offset = $groupIndex * $this->groupOffset($rotation);
        $positionInCycle = ($daysSinceAnchor + $offset) % $rotation->cycle_length;

        if ($positionInCycle < 0) {
            $positionInCycle += $rotation->cycle_length;
        }

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
        $offset = $groupIndex * $this->groupOffset($rotation);
        $positionInCycle = ($daysSinceAnchor + $offset) % $cycleLength;

        if ($positionInCycle < 0) {
            $positionInCycle += $cycleLength;
        }

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
     *     source: string,
     *     grace_minutes: ?int,
     *     early_margin: ?int,
     *     overtime_enabled: bool,
     *     work_on_holidays: bool,
     *     is_overnight: bool,
     *     break_minutes: int,
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
        array $timesMeta = [],
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
            'grace_minutes' => $timesMeta['grace_minutes'] ?? null,
            'early_margin' => $timesMeta['early_margin'] ?? null,
            'overtime_enabled' => $timesMeta['overtime_enabled'] ?? false,
            'work_on_holidays' => $timesMeta['work_on_holidays'] ?? false,
            'is_overnight' => $timesMeta['is_overnight'] ?? false,
            'break_minutes' => $timesMeta['break_minutes'] ?? 0,
        ];
    }

    /**
     * Resolve expected check-in/out times from rotation group snapshot or time schedule.
     *
     * @return array{
     *     check_in: ?string,
     *     check_out: ?string,
     *     is_overnight: bool,
     *     late_margin: ?int,
     *     early_margin: ?int,
     *     break_minutes: int,
     * }
     */
    public function resolveTimes(RotationAssignment $assignment): array
    {
        $snapshot = $assignment->snapshot_data;

        $expectedIn = $snapshot['time_schedule']['in_time'] ?? null;
        $expectedOut = $snapshot['time_schedule']['out_time'] ?? null;
        $isMultiDay = $snapshot['time_schedule']['is_multi_day'] ?? null;
        $lateMargin = $snapshot['time_schedule']['late_margin'] ?? null;
        $earlyMargin = $snapshot['time_schedule']['early_margin'] ?? null;
        $breaksData = $snapshot['time_schedule']['breaks'] ?? null;

        if ($expectedIn && $expectedOut) {
            $checkIn = substr((string) $expectedIn, 0, 5);
            $checkOut = substr((string) $expectedOut, 0, 5);

            return [
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'is_overnight' => $isMultiDay ?? ($checkOut < $checkIn),
                'late_margin' => $lateMargin !== null ? (int) $lateMargin : null,
                'early_margin' => $earlyMargin !== null ? (int) $earlyMargin : null,
                'break_minutes' => is_array($breaksData)
                    ? $this->sumBreakMinutes($breaksData)
                    : 0,
            ];
        }

        $timeSchedule = $assignment->rotationGroup?->timeSchedule;

        if ($timeSchedule) {
            $inTime = $timeSchedule->in_time;
            $outTime = $timeSchedule->out_time;

            // time columns may be returned as strings or Carbon instances depending on the driver
            $checkIn = $inTime ? $this->formatTime($inTime) : null;
            $checkOut = $outTime ? $this->formatTime($outTime) : null;

            $breaks = $timeSchedule->breaks->map(fn ($b) => [
                'break_start' => $b->break_start,
                'break_end' => $b->break_end,
                'duration' => $b->duration,
            ])->toArray();

            return [
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'is_overnight' => $timeSchedule->is_multi_day ?? ($checkOut && $checkIn && $checkOut < $checkIn),
                'late_margin' => $timeSchedule->late_margin !== null ? (int) $timeSchedule->late_margin : null,
                'early_margin' => $timeSchedule->early_margin !== null ? (int) $timeSchedule->early_margin : null,
                'break_minutes' => $this->sumBreakMinutes($breaks),
            ];
        }

        return [
            'check_in' => null,
            'check_out' => null,
            'is_overnight' => false,
            'late_margin' => null,
            'early_margin' => null,
            'break_minutes' => 0,
        ];
    }

    /**
     * Format a time value (string or Carbon) as H:i.
     */
    private function formatTime(mixed $time): ?string
    {
        if (! $time) {
            return null;
        }

        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        $time = (string) $time;

        if (preg_match('/^(\d{2}:\d{2})/', $time, $matches) === 1) {
            return $matches[1];
        }

        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Sum the total break minutes from an array of break entries.
     *
     * @param  array<int, array{duration?: int, break_start?: string, break_end?: string}>  $breaks
     */
    private function sumBreakMinutes(array $breaks): int
    {
        $total = 0;

        foreach ($breaks as $break) {
            if (! empty($break['duration'])) {
                $total += (int) $break['duration'];
            } elseif (! empty($break['break_start']) && ! empty($break['break_end'])) {
                $start = Carbon::parse($break['break_start']);
                $end = Carbon::parse($break['break_end']);
                $total += (int) $start->diffInMinutes($end);
            }
        }

        return $total;
    }
}
