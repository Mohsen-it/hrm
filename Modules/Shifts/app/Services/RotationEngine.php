<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;

/**
 * RotationEngine — the core calculation engine for rotation schedules.
 *
 * Given a rotation definition, a group, and a target date,
 * it determines whether the employee should work or rest using
 * closed-form date math (no loops, no database queries).
 *
 * Anchor selection:
 *   - If the group has a `start_date`, that date is used as the cycle anchor
 *     for that group. This lets each group start its cycle on a different day.
 *   - Otherwise the rotation's `anchor_start_date` is used, with an offset of
 *     `group_index * work_days_count` so that groups tile the cycle without
 *     overlap when number_of_groups × work_days_count <= cycle_length.
 *
 * Formula (with group start_date):
 *   position_in_cycle = (target_date - group.start_date) % cycle_length
 *   is_work_day = pattern[position_in_cycle] == 1
 *
 * Formula (fallback, without group start_date):
 *   position_in_cycle = ((target_date - rotation.anchor_start_date) + (group_index * work_days_count)) % cycle_length
 *   is_work_day = pattern[position_in_cycle] == 1
 */
class RotationEngine
{
    /**
     * Resolve the cycle anchor for a specific group.
     *
     * Returns the group's own start_date if set; otherwise falls back to the
     * rotation's anchor_start_date.
     */
    private function resolveAnchor(Rotation $rotation, RotationGroup $group): Carbon
    {
        if ($group->start_date) {
            return Carbon::parse($group->start_date)->startOfDay();
        }

        return $rotation->anchor_start_date->startOfDay();
    }

    /**
     * Resolve the cycle offset for a given group.
     *
     * Only used when the group does not have its own start_date. Each group
     * works a contiguous block of `work_days_count` days, evenly distributed
     * across the cycle by multiplying the group index by the number of work days.
     */
    private function groupOffset(Rotation $rotation, RotationGroup $group): int
    {
        if ($group->start_date) {
            return 0;
        }

        $workDaysCount = $rotation->work_days_count;

        if (! $workDaysCount) {
            $pattern = is_array($rotation->pattern) ? $rotation->pattern : [];
            $workDaysCount = count(array_filter($pattern, fn ($v) => $v == 1));
        }

        return (int) ($group->group_index ?? 0) * (int) $workDaysCount;
    }

    /**
     * Determine if a date is a work day for a given rotation and group.
     */
    public function isWorkDay(Rotation $rotation, RotationGroup $group, Carbon|string $targetDate): bool
    {
        $date = Carbon::parse($targetDate)->startOfDay();
        $anchor = $this->resolveAnchor($rotation, $group);
        $pattern = $rotation->pattern;

        if (! is_array($pattern) || $rotation->cycle_length <= 0) {
            return false;
        }

        $daysSinceAnchor = (int) $date->diffInDays($anchor);
        $offset = $this->groupOffset($rotation, $group);
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
    public function dayIndex(Rotation $rotation, RotationGroup $group, Carbon|string $targetDate): ?int
    {
        $date = Carbon::parse($targetDate)->startOfDay();
        $anchor = $this->resolveAnchor($rotation, $group);
        $cycleLength = $rotation->cycle_length;

        if ($cycleLength <= 0) {
            return null;
        }

        $daysSinceAnchor = (int) $date->diffInDays($anchor);
        $offset = $this->groupOffset($rotation, $group);
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
    public function getScheduleInRange(Rotation $rotation, RotationGroup $group, Carbon|string $fromDate, Carbon|string $toDate): array
    {
        $current = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->startOfDay();
        $schedule = [];

        while ($current->lte($end)) {
            $schedule[] = [
                'date' => $current->format('Y-m-d'),
                'is_work_day' => $this->isWorkDay($rotation, $group, $current),
                'day_index' => $this->dayIndex($rotation, $group, $current) ?? 0,
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
    public function getWorkDaysInRange(Rotation $rotation, RotationGroup $group, Carbon|string $fromDate, Carbon|string $toDate): array
    {
        $schedule = $this->getScheduleInRange($rotation, $group, $fromDate, $toDate);

        return array_values(array_column(
            array_filter($schedule, fn (array $day): bool => $day['is_work_day']),
            'date'
        ));
    }

    /**
     * Get the next work day from a given date for a specific rotation and group.
     */
    public function getNextWorkDay(Rotation $rotation, RotationGroup $group, Carbon|string $fromDate): Carbon
    {
        $next = Carbon::parse($fromDate)->startOfDay()->addDay();

        // Defensive bound: with a valid pattern a work day always appears
        // within one full cycle, so this can never spin forever even if the
        // rotation data is corrupted (e.g. an all-rest pattern). For valid
        // rotations the returned date is identical to the old loop.
        $maxAttempts = max(1, (int) $rotation->cycle_length) + 1;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($this->isWorkDay($rotation, $group, $next)) {
                return $next;
            }

            $next->addDay();
        }

        return $next;
    }

    /**
     * Get the next rest day from a given date for a specific rotation and group.
     */
    public function getNextRestDay(Rotation $rotation, RotationGroup $group, Carbon|string $fromDate): Carbon
    {
        $next = Carbon::parse($fromDate)->startOfDay()->addDay();

        // Defensive bound: with a valid pattern a rest day always appears
        // within one full cycle, so this can never spin forever even if the
        // rotation data is corrupted (e.g. an all-work pattern). For valid
        // rotations the returned date is identical to the old loop.
        $maxAttempts = max(1, (int) $rotation->cycle_length) + 1;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if (! $this->isWorkDay($rotation, $group, $next)) {
                return $next;
            }

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
     *     in_ahead_margin: ?string,
     *     in_above_margin: ?string,
     *     out_ahead_margin: ?string,
     *     out_above_margin: ?string,
     *     next_day_out_ahead_margin: ?string,
     *     next_day_out_above_margin: ?string,
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
            'is_work_day' => $this->isWorkDay($rotation, $group, $date),
            'status' => $this->isWorkDay($rotation, $group, $date)
                ? ScheduleResolverService::STATUS_WORK
                : ScheduleResolverService::STATUS_REST,
            'expected_check_in' => $expectedCheckIn,
            'expected_check_out' => $expectedCheckOut,
            'day_index' => $this->dayIndex($rotation, $group, $date),
            'cycle_length' => $rotation->cycle_length,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $group->id,
            'exception_id' => $exceptionId,
            'source' => 'rotation',
            'grace_minutes' => $timesMeta['grace_minutes'] ?? null,
            'early_margin' => $timesMeta['early_margin'] ?? null,
            'in_ahead_margin' => $timesMeta['in_ahead_margin'] ?? null,
            'in_above_margin' => $timesMeta['in_above_margin'] ?? null,
            'out_ahead_margin' => $timesMeta['out_ahead_margin'] ?? null,
            'out_above_margin' => $timesMeta['out_above_margin'] ?? null,
            'next_day_out_ahead_margin' => $timesMeta['next_day_out_ahead_margin'] ?? null,
            'next_day_out_above_margin' => $timesMeta['next_day_out_above_margin'] ?? null,
            'overtime_enabled' => $timesMeta['overtime_enabled'] ?? false,
            'work_on_holidays' => $timesMeta['work_on_holidays'] ?? false,
            'is_overnight' => $timesMeta['is_overnight'] ?? false,
            'break_minutes' => $timesMeta['break_minutes'] ?? 0,
        ];
    }

    /**
     * Resolve expected check-in/out times and punch windows for an assignment.
     *
     * Expected times are sourced from the LIVE time schedule — the single
     * source of truth the user maintains on the Time Schedules page. The
     * assignment snapshot is only a fallback for rotations whose schedule was
     * unlinked after the assignment, because snapshots freeze the schedule at
     * assignment time and go stale the moment the user edits /time-schedules.
     *
     * Punch-window edges (in_ahead_margin … out_above_margin) resolve in this
     * priority:
     *   1. the legacy absolute window times stored on the rotation (H:i) when
     *      present — backward compatible with pre-migration data (single-day
     *      schedules and the same-day exit window of overnight duties);
     *   2. the time-schedule margin (integer minutes before in_time / after
     *      out_time) when the schedule carries one;
     *   3. null when neither source provides a value.
     *
     * Overnight (multi-day) duties additionally expose a next-day exit window
     * (next_day_out_*): the departure morning derived from the schedule's
     * out_time ± margins. It lets the punch classifiers recognize the morning
     * punch on the first rest day after a duty as the check-out of that duty
     * instead of a phantom new check-in.
     *
     * @return array{
     *     check_in: ?string,
     *     check_out: ?string,
     *     is_overnight: bool,
     *     late_margin: ?int,
     *     early_margin: ?int,
     *     break_minutes: int,
     *     in_ahead_margin: ?string,
     *     in_above_margin: ?string,
     *     out_ahead_margin: ?string,
     *     out_above_margin: ?string,
     *     next_day_out_ahead_margin: ?string,
     *     next_day_out_above_margin: ?string,
     * }
     */
    public function resolveTimes(RotationAssignment $assignment): array
    {
        $timeSchedule = $assignment->rotation?->timeSchedule;
        $snapshot = $assignment->snapshot_data;
        $snapshotSchedule = is_array($snapshot['time_schedule'] ?? null) ? $snapshot['time_schedule'] : null;

        // Live schedule first — it is the user's source of truth.
        if ($timeSchedule) {
            return $this->resolveTimesFromSchedule([
                'in_time' => $timeSchedule->in_time,
                'out_time' => $timeSchedule->out_time,
                'is_multi_day' => (bool) $timeSchedule->is_multi_day,
                'late_margin' => $timeSchedule->late_margin,
                'early_margin' => $timeSchedule->early_margin,
                'in_ahead_margin' => $timeSchedule->in_ahead_margin,
                'in_above_margin' => $timeSchedule->in_above_margin,
                'out_ahead_margin' => $timeSchedule->out_ahead_margin,
                'out_above_margin' => $timeSchedule->out_above_margin,
                'breaks' => $this->liveBreaks($assignment),
            ], $assignment);
        }

        // Snapshot fallback — only when the rotation no longer has a live schedule.
        if ($snapshotSchedule && ! empty($snapshotSchedule['in_time']) && ! empty($snapshotSchedule['out_time'])) {
            return $this->resolveTimesFromSchedule([
                'in_time' => $snapshotSchedule['in_time'],
                'out_time' => $snapshotSchedule['out_time'],
                'is_multi_day' => (bool) ($snapshotSchedule['is_multi_day'] ?? false),
                'late_margin' => $snapshotSchedule['late_margin'] ?? null,
                'early_margin' => $snapshotSchedule['early_margin'] ?? null,
                'in_ahead_margin' => $snapshotSchedule['in_ahead_margin'] ?? null,
                'in_above_margin' => $snapshotSchedule['in_above_margin'] ?? null,
                'out_ahead_margin' => $snapshotSchedule['out_ahead_margin'] ?? null,
                'out_above_margin' => $snapshotSchedule['out_above_margin'] ?? null,
                'breaks' => is_array($snapshotSchedule['breaks'] ?? null)
                    ? $snapshotSchedule['breaks']
                    : $this->liveBreaks($assignment),
            ], $assignment);
        }

        return [
            'check_in' => null,
            'check_out' => null,
            'is_overnight' => false,
            'late_margin' => null,
            'early_margin' => null,
            'break_minutes' => 0,
            'in_ahead_margin' => $this->legacyWindowTime($this->legacyRotationValue($assignment, 'in_ahead_margin')),
            'in_above_margin' => $this->legacyWindowTime($this->legacyRotationValue($assignment, 'in_above_margin')),
            'out_ahead_margin' => $this->legacyWindowTime($this->legacyRotationValue($assignment, 'out_ahead_margin')),
            'out_above_margin' => $this->legacyWindowTime($this->legacyRotationValue($assignment, 'out_above_margin')),
            'next_day_out_ahead_margin' => null,
            'next_day_out_above_margin' => null,
        ];
    }

    /**
     * Build the resolveTimes payload from one schedule source (live or snapshot).
     *
     * Overnight (multi-day) schedules derive the check-in window from the
     * schedule margins — legacy rotation windows on those rotations describe
     * a pre-migration same-day model and would miss the early 07:30-08:00
     * duty check-ins. The same-day exit window keeps legacy priority (the
     * evening punch that ends the duty), and the departure-morning exit window
     * is exposed separately via next_day_out_*.
     *
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>
     */
    private function resolveTimesFromSchedule(array $schedule, RotationAssignment $assignment): array
    {
        $checkIn = $this->formatTime($schedule['in_time'] ?? null);
        $checkOut = $this->formatTime($schedule['out_time'] ?? null);
        $isMultiDay = (bool) ($schedule['is_multi_day'] ?? ($checkOut && $checkIn && $checkOut < $checkIn));
        $breaks = is_array($schedule['breaks'] ?? null) ? $schedule['breaks'] : [];

        return [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'is_overnight' => $isMultiDay,
            'late_margin' => $this->firstPositiveInt($schedule['late_margin'] ?? null),
            'early_margin' => $this->firstPositiveInt($schedule['early_margin'] ?? null),
            'break_minutes' => $this->sumBreakMinutes($breaks),
            'in_ahead_margin' => $this->resolveWindowEdge(
                $checkIn,
                $schedule['in_ahead_margin'] ?? null,
                $this->legacyRotationValue($assignment, 'in_ahead_margin'),
                ahead: true,
                preferSchedule: $isMultiDay,
            ),
            'in_above_margin' => $this->resolveWindowEdge(
                $checkIn,
                $schedule['in_above_margin'] ?? null,
                $this->legacyRotationValue($assignment, 'in_above_margin'),
                ahead: false,
                preferSchedule: $isMultiDay,
            ),
            'out_ahead_margin' => $this->resolveWindowEdge(
                $checkOut,
                $schedule['out_ahead_margin'] ?? null,
                $this->legacyRotationValue($assignment, 'out_ahead_margin'),
                ahead: true,
            ),
            'out_above_margin' => $this->resolveWindowEdge(
                $checkOut,
                $schedule['out_above_margin'] ?? null,
                $this->legacyRotationValue($assignment, 'out_above_margin'),
                ahead: false,
            ),
            'next_day_out_ahead_margin' => $isMultiDay
                ? $this->relativeWindowEdge($checkOut, $schedule['out_ahead_margin'] ?? null, ahead: true)
                : null,
            'next_day_out_above_margin' => $isMultiDay
                ? $this->relativeWindowEdge($checkOut, $schedule['out_above_margin'] ?? null, ahead: false)
                : null,
        ];
    }

    /**
     * Integer-cast a margin value, preserving 0 but treating empty as null.
     */
    private function firstPositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Resolve one punch-window edge as an absolute H:i time.
     *
     * Priority: the legacy rotation window fields (att_rotations) — absolute
     * H:i times configured on the rotation page — are the primary source of
     * truth for punch windows, exactly as they were before the time-schedule
     * migration. The time-schedule margins (integer minutes relative to
     * in_time / out_time) are only used as a fallback when the rotation does
     * not carry a window on that side.
     *
     * Overnight schedules pass preferSchedule=true for the check-in side: the
     * schedule margins define the actual entry window (legacy rotations carry
     * a same-day 09:30-12:00 window that misses the real 07:30-08:00 duty
     * check-ins), while the same-day exit window keeps legacy priority.
     */
    private function resolveWindowEdge(?string $anchor, mixed $scheduleMargin, mixed $legacyMargin, bool $ahead, bool $preferSchedule = false): ?string
    {
        // Legacy absolute window time (e.g. "07:00:00") wins when present —
        // unless the schedule explicitly takes priority for this edge.
        if (! $preferSchedule && $legacyMargin !== null && $legacyMargin !== '') {
            if (is_string($legacyMargin) && preg_match('/^\d{1,2}:\d{2}/', $legacyMargin) === 1) {
                return substr($legacyMargin, 0, 5);
            }
        }

        $margin = ($scheduleMargin !== null && $scheduleMargin !== '' && (int) $scheduleMargin !== 0)
            ? $scheduleMargin
            : $legacyMargin;

        if ($margin === null || $margin === '') {
            return null;
        }

        // Legacy absolute window time (e.g. "07:00:00") — keep as-is.
        if (is_string($margin) && preg_match('/^\d{1,2}:\d{2}/', $margin) === 1) {
            return substr($margin, 0, 5);
        }

        $minutes = (int) $margin;

        if ($minutes <= 0 || ! $anchor || preg_match('/^\d{1,2}:\d{2}/', $anchor) !== 1) {
            return null;
        }

        $time = Carbon::createFromFormat('H:i', $anchor);

        if (! $time) {
            return null;
        }

        $time = $ahead ? $time->subMinutes($minutes) : $time->addMinutes($minutes);

        return $time->format('H:i');
    }

    /**
     * Resolve an exit-window edge relative to the schedule's out_time, using
     * only integer minute margins (never legacy absolute H:i window times).
     *
     * This powers the departure-morning window of overnight duties: the
     * schedule's out_time (e.g. 08:00 next day) ± its margins yields the
     * morning window on the first rest day after the duty.
     */
    private function relativeWindowEdge(?string $anchor, mixed $margin, bool $ahead): ?string
    {
        if ($margin === null || $margin === '' || (int) $margin <= 0) {
            return null;
        }

        if (is_string($margin) && preg_match('/^\d{1,2}:\d{2}/', $margin) === 1) {
            return null;
        }

        if (! $anchor || preg_match('/^\d{1,2}:\d{2}/', $anchor) !== 1) {
            return null;
        }

        $time = Carbon::createFromFormat('H:i', $anchor);

        if (! $time) {
            return null;
        }

        $time = $ahead ? $time->subMinutes((int) $margin) : $time->addMinutes((int) $margin);

        return $time->format('H:i');
    }

    /**
     * Read a legacy rotation margin field from the live rotation row first,
     * falling back to the assignment snapshot.
     *
     * The rotation page and the time-schedule page are the user's source of
     * truth — editing them must take effect immediately. Assignment snapshots
     * freeze the rotation at assignment time and routinely go stale (e.g. an
     * exit window changed from 14:30-18:00 to 18:00-23:59), so they are only
     * used for rotations whose live row no longer carries the field.
     */
    private function legacyRotationValue(RotationAssignment $assignment, string $field): mixed
    {
        $live = $assignment->rotation?->{$field};

        if ($live !== null && $live !== '') {
            return $live;
        }

        $snapshot = $assignment->snapshot_data;

        if (is_array($snapshot['rotation'] ?? null) && array_key_exists($field, $snapshot['rotation'])) {
            return $snapshot['rotation'][$field] ?? null;
        }

        return null;
    }

    /**
     * Normalize a legacy rotation margin (H:i[:s] time string) to H:i.
     */
    private function legacyWindowTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;

        if (preg_match('/^\d{1,2}:\d{2}/', $value, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Read the live breaks from the assignment's current time schedule.
     *
     * @return array<int, array{break_start?: mixed, break_end?: mixed, duration?: mixed}>|null
     */
    private function liveBreaks(RotationAssignment $assignment): ?array
    {
        $timeSchedule = $assignment->rotation?->timeSchedule;

        if (! $timeSchedule) {
            return null;
        }

        return $timeSchedule->breaks->map(fn ($b) => [
            'break_start' => $b->break_start,
            'break_end' => $b->break_end,
            'duration' => $b->duration,
        ])->values()->all();
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
