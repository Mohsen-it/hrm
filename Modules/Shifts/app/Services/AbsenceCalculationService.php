<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Holidays\Models\Holiday;
use Modules\Shifts\Models\RotationAssignment;
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

        // A raw device punch is physical proof of presence even when the
        // session pipeline could not create an attendance session for this
        // date (e.g. an early-morning punch outside the configured check-in
        // window that was attached to a previous day's still-open session).
        $punchedIds = $punchedIds->merge(
            RawAttendanceLog::query()
                ->whereIn('user_id', $expected->toArray())
                ->whereBetween('punch_time', $this->localDayUtcBounds($dateStr))
                ->distinct()
                ->pluck('user_id')
        )->unique()->values();

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

        // An open session (checked in, never checked out) blocks absence only
        // when its day was an expected work day under the assignment active on
        // that date AND its exit window has passed — mirroring the daily
        // report's missing check-out (incomplete) handling exactly. Open
        // sessions on rest days or on days with no assignment do not block
        // absence, so those employees stay reported absent.
        $openSessions = AttendanceSession::query()
            ->whereIn('user_id', $absent->toArray())
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->whereDate('attendance_date', '<', $dateStr)
            ->orderByDesc('attendance_date')
            ->orderByDesc('check_in_at')
            ->get(['user_id', 'attendance_date', 'check_in_at', 'expected_check_out'])
            ->groupBy('user_id');

        $openSessionIds = collect();
        foreach ($openSessions as $employeeId => $sessions) {
            $mostRecent = $sessions->first();
            if (! $mostRecent) {
                continue;
            }
            $dueDate = $mostRecent->attendance_date?->toDateString();
            $dueAssignment = $this->rotationAssignmentRepository->getAssignmentForDate((int) $employeeId, $dueDate);
            if ($dueAssignment
                && $this->rotationEngine->isWorkDay($dueAssignment->rotation, $dueAssignment->rotationGroup, $dueDate)
                && $this->openSessionWindowPassed($dueDate, $mostRecent, $dueAssignment)) {
                $openSessionIds->push((int) $employeeId);
            }
        }

        $absent = $absent->diff($openSessionIds)->values();

        // Official holidays excuse only the employees they actually cover: the
        // employee's rotation must not work on holidays AND the holiday must
        // apply to their branch/department (or to everyone). Recurring and
        // multi-day holidays are handled here too — matching the daily report
        // and the operational snapshot, instead of the old blanket "any
        // holiday cancels all absence" rule.
        if ($absent->isNotEmpty()) {
            $holidays = Holiday::active()->get();

            if ($holidays->isNotEmpty()) {
                $absentUsers = DB::table('users')
                    ->whereIn('id', $absent->toArray())
                    ->get(['id', 'branch_id', 'department_id'])
                    ->keyBy('id');

                $assignments = $this->rotationAssignmentRepository->getLatestActiveAssignments()
                    ->keyBy('employee_id');

                $holidayExcusedIds = $absent->filter(function (int $employeeId) use ($holidays, $absentUsers, $assignments, $dateStr): bool {
                    $employee = $absentUsers->get($employeeId);

                    if (! $employee) {
                        return false;
                    }

                    if ((bool) ($assignments->get($employeeId)?->rotation?->work_on_holidays ?? false)) {
                        return false;
                    }

                    return $this->hasApplicableHoliday($holidays, $dateStr, $employee);
                });

                $absent = $absent->diff($holidayExcusedIds)->values();
            }
        }

        return $absent;
    }

    /**
     * Determine absence days for a given employee in a specific month.
     *
     * Status values: present | absent | on_leave | holiday | incomplete.
     *
     * @return array<int, array{date: string, status: string, expected_time: ?string}>
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
            ->first(['id', 'branch_id', 'department_id', 'attendance_exemption_type', 'attendance_exemption_from', 'attendance_exemption_to']);

        if (! $employee) {
            return [];
        }

        $rotationAssignment = $this->rotationAssignmentRepository->getActiveAssignment($employeeId);

        if (! $rotationAssignment) {
            return [];
        }

        $rotation = $rotationAssignment->rotation;
        $group = $rotationAssignment->rotationGroup;

        $times = $this->rotationEngine->resolveTimes($rotationAssignment);
        $expectedTime = $times['check_in'] ?? null;
        $holidays = Holiday::active()->get();
        // Open sessions are evaluated per session exactly like the daily
        // report: they only mark following days as "incomplete" (missing
        // check-out) when their day was an expected work day of the assignment
        // active on that date and the exit window has passed. Ordered most
        // recent first for cheap per-day lookups.
        $openSessionFlags = collect();
        $openSessions = AttendanceSession::query()
            ->where('user_id', $employeeId)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->orderByDesc('attendance_date')
            ->orderByDesc('check_in_at')
            ->get(['attendance_date', 'check_in_at', 'expected_check_out']);
        foreach ($openSessions as $open) {
            $dueDate = $open->attendance_date?->toDateString();
            if (! $dueDate) {
                continue;
            }
            $dueAssignment = $this->rotationAssignmentRepository->getAssignmentForDate($employeeId, $dueDate);
            $openSessionFlags->push([
                'date' => $dueDate,
                'blocks' => $dueAssignment !== null
                    && $this->rotationEngine->isWorkDay($dueAssignment->rotation, $dueAssignment->rotationGroup, $dueDate)
                    && $this->openSessionWindowPassed($dueDate, $open, $dueAssignment),
            ]);
        }

        $current = $startOfMonth->copy();
        while ($current->lte($endOfMonth)) {
            $dateStr = $current->toDateString();
            if ($this->isAttendanceExempt($employee, $dateStr)) {
                $current->addDay();

                continue;
            }

            $isExpected = $this->rotationEngine->isWorkDay($rotation, $group, $current);

            if ($isExpected) {
                // An official holiday excuses the employee only when their
                // rotation does not work on holidays and the holiday applies
                // to their branch/department (or to everyone) — the same rule
                // as the daily report and smart absence. Recurring and
                // multi-day holidays are covered by hasApplicableHoliday().
                $isHoliday = ! (bool) $rotation->work_on_holidays
                    && $this->hasApplicableHoliday($holidays, $dateStr, $employee);

                $hasPunch = AttendanceSession::onDate($dateStr)
                    ->where('user_id', $employeeId)
                    ->exists();

                if (! $hasPunch) {
                    // Fall back to raw device punches: a punch proves physical
                    // presence even when no session was created for the date.
                    $hasPunch = RawAttendanceLog::query()
                        ->where('user_id', $employeeId)
                        ->whereBetween('punch_time', $this->localDayUtcBounds($dateStr))
                        ->exists();
                }

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
                } elseif ($isHoliday) {
                    $status = 'holiday';
                } elseif ($openSessionFlags->first(fn (array $o) => $o['date'] < $dateStr)['blocks'] ?? false) {
                    $status = 'incomplete';
                } elseif (! $hasPunch) {
                    $status = 'absent';
                }

                $result[] = [
                    'date' => $dateStr,
                    'status' => $status,
                    'expected_time' => $expectedTime,
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
     * Official holidays cancel absence only for the covered employees (rotation
     * work_on_holidays + branch/department scope), mirroring the daily report.
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
            ->get(['id', 'branch_id', 'department_id', 'termination_date', 'attendance_exemption_type', 'attendance_exemption_from', 'attendance_exemption_to'])
            ->keyBy('id');

        $activeIds = $activeUsers->keys()->all();

        // Attendance punches grouped by date (one query for the whole range).
        $punchesByDate = AttendanceSession::betweenDates($fromStr, $toStr)
            ->whereIn('user_id', $activeIds)
            ->distinct()
            ->get(['attendance_date', 'user_id'])
            ->groupBy(fn ($row) => $this->dateKey($row->attendance_date))
            ->map(fn ($rows) => $rows->pluck('user_id')->flip());

        // Raw device punches are physical proof of presence too. The session
        // pipeline can attach a punch to a previous day's open session (early
        // morning punches outside the configured check-in window), leaving the
        // expected day without a session - without these, such employees would
        // be wrongly reported as absent.
        $utcFrom = Carbon::parse($fromStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
        $utcTo = Carbon::parse($toStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
        $rawPunchesByDate = DB::table('raw_attendance_logs')
            ->whereIn('user_id', $activeIds)
            ->whereBetween('punch_time', [$utcFrom, $utcTo])
            // Soft-deleted logs are excluded, exactly like the Eloquent model
            // query the rest of the report uses.
            ->whereNull('deleted_at')
            ->get(['punch_time', 'user_id'])
            // Punches are stored in UTC; group them under their local
            // app-timezone date so late-evening UTC punches roll onto the
            // correct day (mirrors localDayUtcBounds()).
            ->groupBy(fn ($row) => $this->localDateFromUtc((string) $row->punch_time))
            ->map(fn ($rows) => $rows->pluck('user_id')->flip());

        $punchesByDate = $punchesByDate->map(function (Collection $ids, string $date) use ($rawPunchesByDate): Collection {
            $rawIds = $rawPunchesByDate->get($date, collect());

            return $rawIds->isEmpty() ? $ids : $ids->union($rawIds);
        })->union($rawPunchesByDate->diffKeys($punchesByDate));

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

        // Official holidays inside the range cancel absence for the covered
        // employees — evaluated per employee below (rotation work_on_holidays
        // + branch/department scope, recurring and multi-day included), exactly
        // like the daily report.
        $holidays = Holiday::active()->get();

        // Open sessions are evaluated per session exactly like the daily
        // report: they only mark the following days as "incomplete" (missing
        // check-out) when their day was an expected work day of the assignment
        // active on that date and the exit window has passed. Assignments are
        // preloaded once and resolved in memory to avoid per-session queries.
        $openSessions = AttendanceSession::query()
            ->whereIn('user_id', $activeIds)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->whereDate('attendance_date', '<', $toStr)
            ->orderByDesc('attendance_date')
            ->orderByDesc('check_in_at')
            ->get(['user_id', 'attendance_date', 'check_in_at', 'expected_check_out'])
            ->groupBy('user_id');

        $openEmployeeIds = $openSessions->keys()->all();
        // Assignments are loaded WITHOUT an end-date lower bound: an open
        // session can predate the report range (e.g. a June session), and the
        // daily report evaluates it via getAssignmentForDate() which has no
        // range restriction either — so both reports must see the same
        // assignment for that session's day.
        $assignmentIndex = $openEmployeeIds === []
            ? collect()
            : RotationAssignment::query()
                ->whereIn('employee_id', $openEmployeeIds)
                ->with(['rotation.timeSchedule', 'rotationGroup'])
                ->where('start_date', '<=', $toStr)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->get()
                ->groupBy('employee_id');

        $openSessionFlagsByUser = $openSessions->map(function (Collection $sessions) use ($assignmentIndex): Collection {
            return $sessions->map(function ($open) use ($assignmentIndex): array {
                $dueDate = $open->attendance_date?->toDateString();
                $dueAssignment = $dueDate
                    ? $assignmentIndex->get((int) $open->user_id, collect())
                        ->first(fn ($a) => $this->dateKey($a->start_date) <= $dueDate
                            && ($a->end_date === null || $this->dateKey($a->end_date) >= $dueDate))
                    : null;

                return [
                    'date' => $dueDate,
                    'blocks' => $dueDate !== null && $dueAssignment !== null
                        && $this->rotationEngine->isWorkDay($dueAssignment->rotation, $dueAssignment->rotationGroup, $dueDate)
                        && $this->openSessionWindowPassed($dueDate, $open, $dueAssignment),
                ];
            });
        });

        $stats = []; // employee_id => ['expected' => int, 'present' => int, 'day_details' => array<int, array{date: string, status: string, label: string}>, 'absent_dates' => array<int, string>]
        $meta = [];  // employee_id => RotationAssignment (most recent in range)

        $current = $from->copy();
        while ($current->lte($to)) {
            $dateStr = $current->toDateString();
            $punchedThatDay = $punchesByDate->get($dateStr, collect())->keys()->flip();

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

                // Official holidays cancel absence but not the expectation.
                // Only employees whose rotation does not work on holidays and
                // whose branch/department is covered are excused — matching the
                // daily report. Coverage (vacation / exception) still takes
                // precedence, again matching the daily report.
                $isHoliday = ! (bool) $rotation->work_on_holidays
                    && $this->hasApplicableHoliday($holidays, $dateStr, $employee);

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
                    } elseif ($isHoliday) {
                        $stats[$employeeId]['day_details'][] = [
                            'date' => $dateStr,
                            'status' => 'holiday',
                            'label' => __('shifts::shifts.official_holiday'),
                        ];
                    } elseif ($openSessionFlagsByUser->get($employeeId, collect())
                        ->first(fn (array $o) => $o['date'] < $dateStr)['blocks'] ?? false) {
                        // A day after a due open session is a missing check-out,
                        // not an absence (matches the daily report).
                        $stats[$employeeId]['day_details'][] = [
                            'date' => $dateStr,
                            'status' => 'incomplete',
                            'label' => __('shifts::shifts.incomplete'),
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
            // Expected times come from RotationEngine::resolveTimes() — the
            // same single source of truth the punch classifier and session
            // pipeline use (assignment snapshot → live time schedule).
            $times = $assignment ? $this->rotationEngine->resolveTimes($assignment) : null;

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
                'expected_in' => $times['check_in'] ?? null,
                'expected_out' => $times['check_out'] ?? null,
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
     * UTC boundary strings covering one full app-timezone day.
     *
     * Raw device punches are stored in UTC while report dates are local, so
     * matching a local date requires shifting the day's bounds to UTC.
     *
     * @return array{0: string, 1: string}
     */
    private function localDayUtcBounds(string $dateStr): array
    {
        $day = Carbon::parse($dateStr);

        return [
            $day->copy()->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            $day->copy()->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert a UTC-stored raw punch timestamp to the app-local date.
     *
     * Raw device punches are stored in UTC while report dates are local;
     * grouping a punch under the wrong date (e.g. a 23:30 UTC punch that is
     * 02:30 the next day locally) would misplace it in monthly summaries.
     */
    private function localDateFromUtc(string $utcTime): string
    {
        return Carbon::parse($utcTime, 'UTC')
            ->setTimezone(config('app.timezone'))
            ->toDateString();
    }

    /**
     * Whether an open session's exit window has already ended.
     *
     * Mirrors DailyReportService::isIncompletePunchDue() + exitWindowEnd() so
     * smart absence and the daily report always agree on which open sessions
     * convert the following days into "incomplete" (missing check-out) rather
     * than absence. The window comes from RotationEngine::resolveTimes() — the
     * same single source of truth the daily report uses.
     */
    private function openSessionWindowPassed(string $date, AttendanceSession $open, mixed $assignment): bool
    {
        $reportDay = Carbon::parse($date)->startOfDay();
        if ($reportDay->gt(now()->startOfDay())) {
            return false;
        }

        $times = $this->rotationEngine->resolveTimes($assignment);
        $expectedCheckOut = $open->expected_check_out
            ? substr((string) $open->expected_check_out, 0, 5)
            : ($times['check_out'] ?? null);
        $windowEndTime = $times['out_above_margin'] ?? null;
        $isOvernight = (bool) ($times['is_overnight'] ?? false);

        $windowEnd = $this->openSessionWindowEnd($date, $expectedCheckOut, $assignment, $windowEndTime, $isOvernight);

        if ($windowEnd === null) {
            // Without a resolvable window a past day is always due.
            return $reportDay->lt(now()->startOfDay());
        }

        return now()->gte($windowEnd);
    }

    /**
     * Resolve the end of an open session's exit window for its own day.
     *
     * @see DailyReportService::exitWindowEnd()
     */
    private function openSessionWindowEnd(string $date, ?string $expectedCheckOut, mixed $assignment, ?string $windowEndTime, bool $isOvernight): ?Carbon
    {
        if ($windowEndTime) {
            $end = Carbon::parse($date.' '.substr((string) $windowEndTime, 0, 5));
            if ($isOvernight) {
                $end = $this->moveOpenSessionWindowToDepartureDay($date, $end, $assignment);
            }

            return $end;
        }

        if (! $expectedCheckOut) {
            return null;
        }

        $marginMinutes = (int) ($assignment?->rotation?->timeSchedule?->out_above_margin ?? 0);
        $expectedOut = Carbon::parse($date.' '.$expectedCheckOut);
        if ($isOvernight) {
            $expectedOut = $this->moveOpenSessionWindowToDepartureDay($date, $expectedOut, $assignment);
        }

        return $expectedOut->addMinutes($marginMinutes);
    }

    /**
     * Move an open session's exit-window time onto the departure morning for
     * overnight / multi-day duty rotations.
     *
     * @see DailyReportService::moveWindowToDepartureDay()
     */
    private function moveOpenSessionWindowToDepartureDay(string $date, Carbon $window, mixed $assignment): Carbon
    {
        $rotation = $assignment?->rotation;
        $group = $assignment?->rotationGroup;

        if ($rotation && $group) {
            $departure = $this->rotationEngine->getNextRestDay($rotation, $group, $date);

            return Carbon::create($departure->year, $departure->month, $departure->day, $window->hour, $window->minute, $window->second);
        }

        return $window->addDay();
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
