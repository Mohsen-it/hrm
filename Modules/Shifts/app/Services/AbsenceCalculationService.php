<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Holidays\Models\Holiday;
use Modules\Shifts\Models\ShiftException;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Users\Models\User;
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

        // Smart absence is an operational report: always calculate against
        // the employee's latest open assignment, even when an old date range
        // still has a closed history row for a previous rotation/group.
        $rotationAssignments = $this->rotationAssignmentRepository->getLatestActiveAssignments();
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
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('termination_date')
                    ->orWhere('termination_date', '>=', $dateStr);
            });

        $this->excludeAttendanceExemptions($query, $dateStr);

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        return $query->pluck('id');
    }

    /**
     * Build the live operational attendance picture for a single day.
     *
     * Unlike a report based solely on persisted daily summaries, this method
     * evaluates the rotation assigned on the target day and reads the current
     * biometric sessions.  A worker is required only when their rotation says
     * work and they are not covered by an approved leave, mission, training,
     * swap, or applicable official holiday.
     *
     * An employee without a punch becomes absent only after their expected
     * check-in (including grace) has passed.  Before then they are awaiting
     * arrival, rather than prematurely reported as absent.
     *
     * @return array{
     *     date: string, employees: int, scheduled: int, required: int,
     *     present: int, absent: int, awaiting_arrival: int, late: int,
     *     early_leave: int, missing_punch: int, on_leave: int, on_mission: int,
     *     on_training: int, on_swap: int, on_holiday: int, on_rest: int,
     *     unassigned: int, by_status: array<string, int>
     * }
     */
    public function getOperationalSnapshot(Carbon|CarbonImmutable $date): array
    {
        $date = $date->copy()->startOfDay();
        $dateStr = $date->toDateString();
        $now = Carbon::now($date->getTimezone());

        $employees = DB::table('users')
            ->select(['id', 'branch_id', 'department_id', 'attendance_exemption_type', 'attendance_exemption_from', 'attendance_exemption_to'])
            ->where('id', '!=', User::SUPER_ADMIN_ID)
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->where(fn ($query) => $query->whereNull('termination_date')->orWhere('termination_date', '>=', $dateStr));

        $this->excludeAttendanceExemptions($employees, $dateStr);

        $employees = $employees->get()
            ->keyBy('id');

        $employeeIds = $employees->keys()->map(fn ($id) => (int) $id)->all();
        $base = [
            'date' => $dateStr, 'employees' => count($employeeIds), 'scheduled' => 0, 'required' => 0,
            'present' => 0, 'absent' => 0, 'awaiting_arrival' => 0, 'late' => 0,
            'early_leave' => 0, 'missing_punch' => 0, 'on_leave' => 0, 'on_mission' => 0,
            'on_training' => 0, 'on_swap' => 0, 'on_holiday' => 0, 'on_rest' => 0,
            'unassigned' => 0,
        ];

        if ($employeeIds === []) {
            return $base + ['by_status' => []];
        }

        $assignments = $this->rotationAssignmentRepository->getAssignmentsForDate($dateStr)
            ->whereIn('employee_id', $employeeIds)
            ->sortBy(fn ($assignment) => $assignment->start_date?->getTimestamp() ?? 0)
            ->keyBy('employee_id');

        $vacationIds = UserVacationRequest::query()
            ->where('status', UserVacationRequest::STATUS_APPROVED)
            ->whereIn('user_id', $employeeIds)
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->pluck('user_id')
            ->flip();

        $exceptions = ShiftException::active()
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
            ->overlapping($dateStr)
            ->get(['employee_id', 'exception_type'])
            ->groupBy('employee_id');

        $punches = AttendanceSession::onDate($dateStr)
            ->whereIn('user_id', $employeeIds)
            ->whereNotNull('check_in_at')
            ->selectRaw('user_id, MAX(late_minutes) as late_minutes, MAX(early_leave_minutes) as early_leave_minutes, SUM(CASE WHEN check_in_at IS NOT NULL AND check_out_at IS NULL THEN 1 ELSE 0 END) as open_sessions')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $holidays = Holiday::active()->get();

        foreach ($employeeIds as $employeeId) {
            $employee = $employees->get($employeeId);
            $assignment = $assignments->get($employeeId);
            $exceptionTypes = $exceptions->get($employeeId, collect())->pluck('exception_type')->flip();

            // Coverage is tracked for every employee, even on a rest day, so
            // dashboard leave/mission counters remain a truthful daily roster.
            if ($vacationIds->has($employeeId) || $exceptionTypes->has('leave')) {
                $base['on_leave']++;
            } elseif ($exceptionTypes->has('mission')) {
                $base['on_mission']++;
            } elseif ($exceptionTypes->has('training')) {
                $base['on_training']++;
            } elseif ($exceptionTypes->has('swap')) {
                $base['on_swap']++;
            }

            if (! $assignment) {
                $base['unassigned']++;

                continue;
            }

            $rotation = $assignment->rotation;
            $group = $assignment->rotationGroup;
            if (! $this->rotationEngine->isWorkDay($rotation, $group, $date)) {
                $base['on_rest']++;

                continue;
            }

            $base['scheduled']++;

            $isHoliday = ! (bool) $rotation->work_on_holidays
                && $this->hasApplicableHoliday($holidays, $dateStr, $employee);
            if ($isHoliday) {
                $base['on_holiday']++;

                continue;
            }

            if ($vacationIds->has($employeeId) || $exceptionTypes->isNotEmpty()) {
                continue;
            }

            $base['required']++;
            $punch = $punches->get($employeeId);
            if ($punch) {
                $base['present']++;
                $base['late'] += (int) $punch->late_minutes > 0 ? 1 : 0;
                $base['early_leave'] += (int) $punch->early_leave_minutes > 0 ? 1 : 0;
                $base['missing_punch'] += (int) $punch->open_sessions > 0 ? 1 : 0;

                continue;
            }

            $times = $this->rotationEngine->resolveTimes($assignment);
            $grace = (int) ($rotation->grace_minutes ?: $times['late_margin'] ?: 0);
            $deadline = $times['check_in']
                ? $date->copy()->setTimeFromTimeString($times['check_in'])->addMinutes($grace)
                : $date->copy()->endOfDay();

            if ($now->greaterThan($deadline)) {
                $base['absent']++;
            } else {
                $base['awaiting_arrival']++;
            }
        }

        return $base + [
            'by_status' => [
                'present' => $base['present'], 'absent' => $base['absent'], 'late' => $base['late'],
                'early_leave' => $base['early_leave'], 'missing_punch' => $base['missing_punch'],
                'awaiting_arrival' => $base['awaiting_arrival'], 'vacation' => $base['on_leave'],
                'mission' => $base['on_mission'], 'training' => $base['on_training'], 'swap' => $base['on_swap'],
                'holiday' => $base['on_holiday'], 'rest' => $base['on_rest'], 'unassigned' => $base['unassigned'],
            ],
        ];
    }

    /**
     * Determine whether an active holiday covers this employee on this date.
     *
     * @param  Collection<int, Holiday>  $holidays
     */
    private function hasApplicableHoliday(Collection $holidays, string $date, object $employee): bool
    {
        foreach ($holidays as $holiday) {
            $duration = max(1, (int) $holiday->duration_days);
            $anchor = $holiday->is_recurring
                ? Carbon::createFromDate(
                    Carbon::parse($date)->year,
                    (int) $holiday->recurring_month,
                    (int) $holiday->recurring_day,
                )
                : $holiday->date?->copy()->startOfDay();

            if (! $anchor || ! Carbon::parse($date)->betweenIncluded($anchor, $anchor->copy()->addDays($duration - 1))) {
                continue;
            }

            if ($holiday->applies_to_all
                || in_array((int) $employee->branch_id, $holiday->applies_to_branches ?? [], true)
                || in_array((int) $employee->department_id, $holiday->applies_to_departments ?? [], true)) {
                return true;
            }
        }

        return false;
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
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->first(['id', 'attendance_exemption_type', 'attendance_exemption_from', 'attendance_exemption_to']);

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
            if ($this->isAttendanceExempt($employee, $dateStr)) {
                $current->addDay();

                continue;
            }

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
     *         present: int,
     *         day_details: array<int, array{date: string, status: string, label: string}>,
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
     *     total_present_days: int,
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

        // This is an operational report, so its roster is always the latest
        // open assignment for each employee, not the historical assignment
        // that was attached to a previous group/rotation.
        $assignments = $this->rotationAssignmentRepository->getLatestActiveAssignments();

        // Active employees, respecting the department filter.
        // id => employment / exemption metadata.
        $activeUsers = DB::table('users')
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->where(function ($q) use ($fromStr) {
                $q->whereNull('termination_date')
                    ->orWhere('termination_date', '>=', $fromStr);
            })
            ->when($departmentId !== null, fn ($q) => $q->where('department_id', $departmentId))
            ->get(['id', 'termination_date', 'attendance_exemption_type', 'attendance_exemption_from', 'attendance_exemption_to'])
            ->keyBy('id');

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
                ->get(['employee_id', 'from_date', 'to_date', 'exception_type'])
        );

        // Official holidays inside the range cancel absence for those days.
        $holidays = Holiday::where('is_active', true)
            ->whereBetween('date', [$fromStr, $toStr])
            ->pluck('date')
            ->map(fn ($date) => $this->dateKey($date))
            ->flip();

        $stats = []; // employee_id => ['expected' => int, 'present' => int, 'day_details' => array<int, array{date: string, status: string, label: string}>, 'absent_dates' => array<int, string>]
        $meta = [];  // employee_id => RotationAssignment (most recent in range)

        $current = $from->copy();
        while ($current->lte($to)) {
            $dateStr = $current->toDateString();
            $isHoliday = $holidays->has($dateStr);

            if (! $isHoliday) {
                $punchedThatDay = $punchesByDate->get($dateStr, collect())->keys()->flip();
            }

            foreach ($assignments as $assignment) {
                $employeeId = $assignment->employee_id;
                $rotation = $assignment->rotation;
                $group = $assignment->rotationGroup;

                // `has()` (unlike isset) treats a null termination_date as a
                // valid key, so employees still employed are not skipped.
                if (! $activeUsers->has($employeeId)) {
                    continue;
                }
                // The employee must still be employed on this exact day.
                $employee = $activeUsers[$employeeId];
                $terminationDate = $employee->termination_date;
                if ($terminationDate !== null && $this->dateKey($terminationDate) < $dateStr) {
                    continue;
                }
                if ($this->isAttendanceExempt($employee, $dateStr)) {
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
                    $stats[$employeeId] = [
                        'expected' => 0,
                        'present' => 0,
                        'day_details' => [],
                        'absent_dates' => [],
                    ];
                }
                $stats[$employeeId]['expected']++;

                // Official holidays cancel absence but not the expectation,
                // matching the daily report behaviour exactly.
                if ($isHoliday) {
                    $stats[$employeeId]['day_details'][] = [
                        'date' => $dateStr,
                        'status' => 'holiday',
                        'label' => __('shifts::shifts.official_holiday'),
                    ];
                    $meta[$employeeId] = $assignment;

                    continue;
                }

                $hasPunch = $punchedThatDay->has($employeeId);

                if ($hasPunch) {
                    $stats[$employeeId]['present']++;
                } else {
                    $coverage = $this->getCoverage($employeeId, $dateStr, $vacations, $exceptions);

                    if ($coverage !== null) {
                        $stats[$employeeId]['day_details'][] = [
                            'date' => $dateStr,
                            'status' => $coverage['type'],
                            'label' => $coverage['label'],
                        ];
                    } else {
                        $stats[$employeeId]['day_details'][] = [
                            'date' => $dateStr,
                            'status' => 'absent',
                            'label' => __('shifts::shifts.absent_short'),
                        ];
                        $stats[$employeeId]['absent_dates'][] = $dateStr;
                    }
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
                'present' => $stat['present'],
                'day_details' => $stat['day_details'],
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
            'total_present_days' => $employees->sum('present'),
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
            $index[(int) $key][] = [
                'from' => $from,
                'to' => $to,
                // Vacation rows carry no exception_type; exceptions do.
                'type' => $row->exception_type ?? 'vacation',
            ];
        }

        return $index;
    }

    /**
     * Determine whether an employee is covered (vacation / exception) on a date
     * and, if so, return the coverage type + translated label.
     *
     * @param  array<int, array<int, array{from: string, to: string, type: ?string}>>  $vacations
     * @param  array<int, array<int, array{from: string, to: string, type: ?string}>>  $exceptions
     * @return array{type: string, label: string}|null
     */
    private function getCoverage(int $employeeId, string $dateStr, array $vacations, array $exceptions): ?array
    {
        foreach (($vacations[$employeeId] ?? []) as $range) {
            if ($range['from'] <= $dateStr && $range['to'] >= $dateStr) {
                return ['type' => 'vacation', 'label' => __('shifts::shifts.on_vacation')];
            }
        }

        foreach (($exceptions[$employeeId] ?? []) as $range) {
            if ($range['from'] <= $dateStr && $range['to'] >= $dateStr) {
                return ['type' => 'exception', 'label' => $this->exceptionLabel($range['type'] ?? null)];
            }
        }

        return null;
    }

    /**
     * Human-readable label for an intercepting shift exception type.
     */
    private function exceptionLabel(?string $type): string
    {
        return match ($type) {
            'leave' => __('shifts::shifts.leave'),
            'mission' => __('shifts::shifts.mission'),
            'swap' => __('shifts::shifts.swap'),
            'training' => __('shifts::shifts.training'),
            default => __('shifts::shifts.on_exception'),
        };
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
     * Exclude an employee while an approved HR attendance-exemption is active.
     */
    private function excludeAttendanceExemptions($query, string $date): void
    {
        $query->where(function ($subQuery) use ($date): void {
            $subQuery->whereNull('attendance_exemption_type')
                ->orWhereNull('attendance_exemption_from')
                ->orWhere('attendance_exemption_from', '>', $date)
                ->orWhere('attendance_exemption_to', '<', $date);
        });
    }

    /**
     * Check whether one employee is exempt from absence reporting on a date.
     */
    private function isAttendanceExempt(object $employee, string $date): bool
    {
        if (! $employee->attendance_exemption_type || ! $employee->attendance_exemption_from) {
            return false;
        }

        $from = $this->dateKey($employee->attendance_exemption_from);
        $to = $employee->attendance_exemption_to
            ? $this->dateKey($employee->attendance_exemption_to)
            : null;

        return $from <= $date && ($to === null || $to >= $date);
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
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->first(['id', 'attendance_exemption_type', 'attendance_exemption_from', 'attendance_exemption_to']);

        if (! $employee || $this->isAttendanceExempt($employee, $date->toDateString())) {
            return false;
        }

        $rotationAssignment = $this->rotationAssignmentRepository
            ->getAssignmentForDate($employeeId, $date->toDateString());

        if ($rotationAssignment) {
            $rotation = $rotationAssignment->rotation;
            $group = $rotationAssignment->rotationGroup;

            return $this->rotationEngine->isWorkDay($rotation, $group, $date);
        }

        return false;
    }
}
