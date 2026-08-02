<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Holidays\Models\Holiday;
use Modules\Shifts\Models\ShiftException;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Vacations\Models\UserVacationRequest;

class AbsenceCalculationService
{
    public function __construct(
        private RotationAssignmentRepository $rotationAssignmentRepository,
        private RotationEngine $rotationEngine,
    ) {}

    /**
     * Normalize a rotation filter (single id or array) into a list of ids.
     *
     * @return array<int, int>
     */
    private function normalizeRotationFilter(int|array|null $rotationIds): array
    {
        if ($rotationIds === null || $rotationIds === '' || $rotationIds === []) {
            return [];
        }

        return collect((array) $rotationIds)
            ->filter(fn ($id) => $id !== null && $id !== '' && $id !== false)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Get employee IDs expected to work on the given date.
     *
     * @param  int|array<int, int>|null  $rotationIds
     * @param  int|array<int, int>|null  $rotationGroupIds
     * @return Collection<int, int>
     */
    public function getExpectedEmployees(
        Carbon $date,
        ?int $departmentId = null,
        int|array|null $rotationIds = null,
        int|array|null $rotationGroupIds = null,
    ): Collection {
        $dateStr = $date->toDateString();
        $rotationIdList = $this->normalizeRotationFilter($rotationIds);
        $groupIdList = $this->normalizeRotationFilter($rotationGroupIds);

        $rotationAssignments = $this->rotationAssignmentRepository->getAssignmentsForDate($dateStr);
        $expectedIds = collect();

        foreach ($rotationAssignments as $rotationAssignment) {
            $rotation = $rotationAssignment->rotation;
            $group = $rotationAssignment->rotationGroup;

            if ($rotationIdList !== [] && ! in_array($rotation->id, $rotationIdList, true)) {
                continue;
            }

            if ($groupIdList !== [] && ! in_array($group->id, $groupIdList, true)) {
                continue;
            }

            if ($this->rotationEngine->isWorkDay($rotation, $group, $date)) {
                $expectedIds->push($rotationAssignment->employee_id);
            }
        }

        $expectedIds = $expectedIds->unique()->values();

        if ($expectedIds->isEmpty()) {
            return $expectedIds;
        }

        $query = DB::table('users')
            ->whereIn('id', $expectedIds->toArray())
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('termination_date')
                    ->orWhere('termination_date', '>=', $dateStr);
            });

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        return $query->pluck('id');
    }

    /**
     * Get the list of absent employees for a given date.
     *
     * @param  int|array<int, int>|null  $rotationIds
     * @param  int|array<int, int>|null  $rotationGroupIds
     * @return Collection<int, int>
     */
    public function getAbsentEmployees(
        Carbon $date,
        ?int $departmentId = null,
        int|array|null $rotationIds = null,
        int|array|null $rotationGroupIds = null,
    ): Collection {
        $expected = $this->getExpectedEmployees($date, $departmentId, $rotationIds, $rotationGroupIds);

        if ($expected->isEmpty()) {
            return collect();
        }

        $dateStr = $date->toDateString();

        $punchedIds = AttendanceSession::onDate($dateStr)
            ->whereIn('user_id', $expected->toArray())
            ->distinct()
            ->pluck('user_id');

        $absent = $expected->diff($punchedIds)->values();

        $onLeaveIds = UserVacationRequest::where('status', UserVacationRequest::STATUS_APPROVED)
            ->whereIn('user_id', $absent->toArray())
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->distinct()
            ->pluck('user_id');

        $interceptedIds = ShiftException::active()
            ->whereIn('employee_id', $absent->toArray())
            ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
            ->where('from_date', '<=', $dateStr)
            ->where('to_date', '>=', $dateStr)
            ->distinct()
            ->pluck('employee_id');

        $absent = $absent->diff($onLeaveIds)->diff($interceptedIds)->values();

        if (Holiday::where('is_active', true)->whereDate('date', $dateStr)->exists()) {
            return collect();
        }

        return $absent;
    }

    /**
     * Determine absence days for a given employee in a specific month.
     *
     * @return array<int, array{date: string, status: string}>
     */
    public function getMonthlyAbsence(int $employeeId, int $month, int $year): array
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $result = [];

        $employee = DB::table('users')
            ->where('id', $employeeId)
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->first();

        if (! $employee) {
            return [];
        }

        $rotationAssignment = $this->rotationAssignmentRepository->getActiveAssignment($employeeId);

        if (! $rotationAssignment) {
            return [];
        }

        $rotation = $rotationAssignment->rotation;
        $group = $rotationAssignment->rotationGroup;

        $current = $startOfMonth->copy();
        while ($current->lte($endOfMonth)) {
            $dateStr = $current->toDateString();
            $isExpected = $this->rotationEngine->isWorkDay($rotation, $group, $current);

            if ($isExpected) {
                $hasPunch = AttendanceSession::onDate($dateStr)
                    ->where('user_id', $employeeId)
                    ->exists();

                $approvedLeave = UserVacationRequest::where('status', UserVacationRequest::STATUS_APPROVED)
                    ->where('user_id', $employeeId)
                    ->where('start_date', '<=', $dateStr)
                    ->where('end_date', '>=', $dateStr)
                    ->exists();

                $intercepted = ShiftException::active()
                    ->where('employee_id', $employeeId)
                    ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
                    ->where('from_date', '<=', $dateStr)
                    ->where('to_date', '>=', $dateStr)
                    ->exists();

                $status = 'present';
                if ($approvedLeave || $intercepted) {
                    $status = 'on_leave';
                } elseif (! $hasPunch) {
                    $status = 'absent';
                }

                $result[] = [
                    'date' => $dateStr,
                    'status' => $status,
                ];
            }

            $current->addDay();
        }

        return $result;
    }

    /**
     * Build a monthly / date-range absence report aggregated per employee.
     *
     * The calculation mirrors getAbsentEmployees() but is evaluated for every
     * day in the range:
     *   - an employee is expected on a day when they have an active rotation
     *     assignment whose rotation + group mark that day as a work day
     *     (RotationEngine), and
     *   - they count as absent when they have no attendance punch that day and
     *     are not covered by an approved vacation or an intercepting shift
     *     exception.
     * Official holidays cancel absence for the whole day.
     *
     * @param  int|array<int, int>|null  $rotationIds
     * @param  int|array<int, int>|null  $rotationGroupIds
     * @return array{
     *     employees: Collection<int, array{
     *         employee_id: int,
     *         expected: int,
     *         absent_dates: array<int, string>,
     *         rotation_id: ?int,
     *         rotation_group_id: ?int,
     *         rotation_name: ?string,
     *         rotation_group_name: ?string,
     *         expected_in: ?string,
     *         expected_out: ?string,
     *     }>,
     *     total_expected_days: int,
     *     total_absent_days: int,
     * }
     */
    public function getMonthlyAbsenceReport(
        Carbon $from,
        Carbon $to,
        ?int $departmentId = null,
        int|array|null $rotationIds = null,
        int|array|null $rotationGroupIds = null,
    ): array {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $rotationIdList = $this->normalizeRotationFilter($rotationIds);
        $groupIdList = $this->normalizeRotationFilter($rotationGroupIds);

        // Assignments overlapping the range (single query, eager loaded).
        $assignments = $this->rotationAssignmentRepository->getAssignmentsOverlapping($fromStr, $toStr);

        // Active employees, respecting the department filter.
        // id => termination_date (null = still employed).
        $activeUsers = DB::table('users')
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->where(function ($q) use ($fromStr) {
                $q->whereNull('termination_date')
                    ->orWhere('termination_date', '>=', $fromStr);
            })
            ->when($departmentId !== null, fn ($q) => $q->where('department_id', $departmentId))
            ->pluck('termination_date', 'id');

        $activeIds = $activeUsers->keys()->all();

        // Attendance punches grouped by date (one query for the whole range).
        $punchesByDate = AttendanceSession::betweenDates($fromStr, $toStr)
            ->whereIn('user_id', $activeIds)
            ->distinct()
            ->get(['attendance_date', 'user_id'])
            ->groupBy(fn ($row) => $this->dateKey($row->attendance_date))
            ->map(fn ($rows) => $rows->pluck('user_id')->flip());

        // Approved vacations overlapping the range.
        $vacations = $this->indexCoverage(
            UserVacationRequest::where('status', UserVacationRequest::STATUS_APPROVED)
                ->whereIn('user_id', $activeIds)
                ->overlapping($fromStr, $toStr)
                ->get(['user_id', 'start_date', 'end_date'])
        );

        // Intercepting shift exceptions overlapping the range.
        $exceptions = $this->indexCoverage(
            ShiftException::active()
                ->whereIn('employee_id', $activeIds)
                ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
                ->where('from_date', '<=', $toStr)
                ->where('to_date', '>=', $fromStr)
                ->get(['employee_id', 'from_date', 'to_date'])
        );

        // Official holidays inside the range cancel absence for those days.
        $holidays = Holiday::where('is_active', true)
            ->whereBetween('date', [$fromStr, $toStr])
            ->pluck('date')
            ->map(fn ($date) => $this->dateKey($date))
            ->flip();

        $stats = []; // employee_id => ['expected' => int, 'absent_dates' => array<int, string>]
        $meta = [];  // employee_id => RotationAssignment (most recent in range)

        $current = $from->copy();
        while ($current->lte($to)) {
            $dateStr = $current->toDateString();
            $isHoliday = $holidays->has($dateStr);

            if (! $isHoliday) {
                $punchedThatDay = $punchesByDate->get($dateStr, collect())->keys()->flip();
            }

            foreach ($assignments as $assignment) {
                // The assignment must be active on this exact day.
                if ($assignment->start_date->greaterThan($dateStr)) {
                    continue;
                }
                if ($assignment->end_date !== null && $assignment->end_date->lessThan($dateStr)) {
                    continue;
                }

                $employeeId = $assignment->employee_id;
                $rotation = $assignment->rotation;
                $group = $assignment->rotationGroup;

                // `has()` (unlike isset) treats a null termination_date as a
                // valid key, so employees still employed are not skipped.
                if (! $activeUsers->has($employeeId)) {
                    continue;
                }
                // The employee must still be employed on this exact day.
                $terminationDate = $activeUsers[$employeeId];
                if ($terminationDate !== null && $this->dateKey($terminationDate) < $dateStr) {
                    continue;
                }
                if ($rotationIdList !== [] && ! in_array($rotation->id, $rotationIdList, true)) {
                    continue;
                }
                if ($groupIdList !== [] && ! in_array($group->id, $groupIdList, true)) {
                    continue;
                }
                if (! $this->rotationEngine->isWorkDay($rotation, $group, $current)) {
                    continue;
                }

                if (! isset($stats[$employeeId])) {
                    $stats[$employeeId] = ['expected' => 0, 'absent_dates' => []];
                }
                $stats[$employeeId]['expected']++;

                // Official holidays cancel absence but not the expectation,
                // matching the daily report behaviour exactly.
                if ($isHoliday) {
                    continue;
                }

                $hasPunch = $punchedThatDay->has($employeeId);
                $covered = $this->isCovered($employeeId, $dateStr, $vacations, $exceptions);

                if (! $hasPunch && ! $covered) {
                    $stats[$employeeId]['absent_dates'][] = $dateStr;
                }

                // Keep the most recent assignment as display metadata
                // (days iterate ascending, so the last one wins).
                $meta[$employeeId] = $assignment;
            }

            $current->addDay();
        }

        $employees = collect($stats)->map(function (array $stat, int $employeeId) use ($meta) {
            $assignment = $meta[$employeeId] ?? null;
            $timeSchedule = $assignment?->rotation?->timeSchedule;

            return [
                'employee_id' => $employeeId,
                'expected' => $stat['expected'],
                'absent_dates' => $stat['absent_dates'],
                'rotation_id' => $assignment?->rotation_id,
                'rotation_group_id' => $assignment?->rotation_group_id,
                'rotation_name' => $assignment?->rotation?->name,
                'rotation_group_name' => $assignment?->rotationGroup?->name,
                'expected_in' => $this->formatExpectedTime($timeSchedule?->in_time),
                'expected_out' => $this->formatExpectedTime($timeSchedule?->out_time),
            ];
        })->values();

        return [
            'employees' => $employees,
            'total_expected_days' => $employees->sum('expected'),
            'total_absent_days' => $employees->sum(fn (array $employee) => count($employee['absent_dates'])),
        ];
    }

    /**
     * Index coverage rows (vacations / exceptions) by entity id for fast
     * per-day lookups.
     *
     * @param  Collection  $rows  Collection of models with a user_id/employee_id and start_date/end_date or from_date/to_date
     * @return array<int, array<int, array{from: string, to: string}>>
     */
    private function indexCoverage(Collection $rows): array
    {
        $index = [];

        foreach ($rows as $row) {
            $key = $row->user_id ?? $row->employee_id;
            $from = $this->dateKey($row->start_date ?? $row->from_date);
            $to = $this->dateKey($row->end_date ?? $row->to_date);
            $index[(int) $key][] = ['from' => $from, 'to' => $to];
        }

        return $index;
    }

    /**
     * Determine whether an employee is covered (vacation / exception) on a date.
     *
     * @param  array<int, array<int, array{from: string, to: string}>>  $vacations
     * @param  array<int, array<int, array{from: string, to: string}>>  $exceptions
     */
    private function isCovered(int $employeeId, string $dateStr, array $vacations, array $exceptions): bool
    {
        foreach (($vacations[$employeeId] ?? []) as $range) {
            if ($range['from'] <= $dateStr && $range['to'] >= $dateStr) {
                return true;
            }
        }

        foreach (($exceptions[$employeeId] ?? []) as $range) {
            if ($range['from'] <= $dateStr && $range['to'] >= $dateStr) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize a date-like value to a Y-m-d string.
     */
    private function dateKey(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    /**
     * Normalize a time value (string or Carbon) to H:i.
     */
    private function formatExpectedTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;

        if (preg_match('/^(\d{2}:\d{2})/', $string, $matches) === 1) {
            return $matches[1];
        }

        try {
            return Carbon::parse($string)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Determine whether a specific employee is expected to work on the given date.
     */
    public function isEmployeeExpectedToWork(int $employeeId, Carbon $date): bool
    {
        $employee = DB::table('users')
            ->where('id', $employeeId)
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->first();

        if (! $employee) {
            return false;
        }

        $rotationAssignment = $this->rotationAssignmentRepository->getActiveAssignment($employeeId);

        if ($rotationAssignment) {
            $rotation = $rotationAssignment->rotation;
            $group = $rotationAssignment->rotationGroup;

            return $this->rotationEngine->isWorkDay($rotation, $group, $date);
        }

        return false;
    }
}
