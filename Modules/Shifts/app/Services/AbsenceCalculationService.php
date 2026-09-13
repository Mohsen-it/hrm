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
    /**
     * Per-instance memo of active holidays (011/P1-B).
     *
     * The service is transient (no singleton binding), so this lives for one
     * resolve only — never stale across requests or queue jobs. Callers must
     * treat the returned collection as READ-ONLY (all current usages iterate
     * or filter into new collections).
     *
     * @var Collection<int, Holiday>|null
     */
    private ?Collection $activeHolidays = null;

    public function __construct(
        private RotationAssignmentRepository $rotationAssignmentRepository,
        private RotationEngine $rotationEngine,
    ) {}

    /**
     * Active holidays, queried once per service instance.
     *
     * @return Collection<int, Holiday>
     */
    private function activeHolidays(): Collection
    {
        return $this->activeHolidays ??= Holiday::active()->get();
    }

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
     * @param  int|array<int, int>|null  $departmentIds
     * @param  int|array<int, int>|null  $rotationIds
     * @param  int|array<int, int>|null  $rotationGroupIds
     * @return Collection<int, int>
     */
    public function getExpectedEmployees(
        Carbon $date,
        int|array|null $departmentIds = null,
        int|array|null $rotationIds = null,
        int|array|null $rotationGroupIds = null,
    ): Collection {
        $dateStr = $date->toDateString();
        $rotationIdList = $this->normalizeRotationFilter($rotationIds);
        $groupIdList = $this->normalizeRotationFilter($rotationGroupIds);

        // Past dates resolve against the historically active assignment so a
        // rotation transfer does not rewrite history; today/future use the
        // latest open assignment (operational view).
        $rotationAssignments = $this->rotationAssignmentRepository->getEffectiveAssignmentsForDate($dateStr);
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
        $this->excludeNotYetHired($query, $dateStr);

        if ($departmentIds !== null) {
            $departmentIds = is_array($departmentIds) ? $departmentIds : [$departmentIds];
        }

        if ($departmentIds !== null && $departmentIds !== []) {
            $query->whereIn('department_id', $departmentIds);
        }

        return $query->pluck('id');
    }

    /**
     * Active employees with no rotation assignment on the given date.
     *
     * These people are invisible to every absence report — never expected,
     * never absent — even when they work and punch every day. Surfacing them
     * lets HR notice untracked workers (and assign them a rotation) instead
     * of leaving their attendance — or their absence — permanently outside
     * every calculation.
     *
     * @return Collection<int, int>
     */
    public function getUnassignedEmployeeIds(Carbon $date): Collection
    {
        $dateStr = $date->toDateString();
        $assignedIds = $this->rotationAssignmentRepository->getEffectiveAssignmentsForDate($dateStr)
            ->pluck('employee_id');

        $query = DB::table('users')
            ->whereNotIn('id', $assignedIds)
            ->where('id', '!=', User::SUPER_ADMIN_ID)
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('termination_date')
                    ->orWhere('termination_date', '>=', $dateStr);
            });

        $this->excludeAttendanceExemptions($query, $dateStr);
        $this->excludeNotYetHired($query, $dateStr);

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
        $this->excludeNotYetHired($employees, $dateStr);

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
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
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

        $holidays = $this->activeHolidays();

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
     * Employees expected today whose check-in deadline has not passed yet.
     *
     * Same coverage rules as getAbsentEmployees() (vacation, exception,
     * holiday), but keeping only the employees still inside their arrival
     * window. Lets the daily page show them explicitly instead of an empty
     * absent list on current-day mornings.
     *
     * @param  int|array<int, int>|null  $departmentIds
     * @param  int|array<int, int>|null  $rotationIds
     * @param  int|array<int, int>|null  $rotationGroupIds
     * @return Collection<int, int>
     */
    public function getAwaitingArrivalEmployees(
        Carbon $date,
        int|array|null $departmentIds = null,
        int|array|null $rotationIds = null,
        int|array|null $rotationGroupIds = null,
    ): Collection {
        $expected = $this->getExpectedEmployees($date, $departmentIds, $rotationIds, $rotationGroupIds);

        if ($expected->isEmpty()) {
            return collect();
        }

        $dateStr = $date->toDateString();
        $present = $this->computePresentIds($date, $expected);
        $waiting = $expected->diff($present)->values();

        if ($waiting->isEmpty()) {
            return $waiting;
        }

        $onLeaveIds = UserVacationRequest::where('status', UserVacationRequest::STATUS_APPROVED)
            ->whereIn('user_id', $waiting->toArray())
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->distinct()
            ->pluck('user_id');

        $interceptedIds = ShiftException::active()
            ->whereIn('employee_id', $waiting->toArray())
            ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
            ->whereDate('from_date', '<=', $dateStr)
            ->whereDate('to_date', '>=', $dateStr)
            ->distinct()
            ->pluck('employee_id');

        $waiting = $waiting->diff($onLeaveIds)->diff($interceptedIds)->values();

        if ($waiting->isEmpty()) {
            return $waiting;
        }

        $holidays = $this->activeHolidays();

        if ($holidays->isNotEmpty()) {
            $waitingUsers = DB::table('users')
                ->whereIn('id', $waiting->toArray())
                ->get(['id', 'branch_id', 'department_id'])
                ->keyBy('id');

            $assignments = $this->rotationAssignmentRepository->getEffectiveAssignmentsForDate($dateStr)
                ->keyBy('employee_id');

            $waiting = $waiting->reject(function (int $employeeId) use ($holidays, $waitingUsers, $assignments, $dateStr): bool {
                $employee = $waitingUsers->get($employeeId);

                if (! $employee) {
                    return false;
                }

                if ((bool) ($assignments->get($employeeId)?->rotation?->work_on_holidays ?? false)) {
                    return false;
                }

                return $this->hasApplicableHoliday($holidays, $dateStr, $employee);
            })->values();
        }

        if ($waiting->isEmpty()) {
            return $waiting;
        }

        $now = Carbon::now();
        $assignments = $this->rotationAssignmentRepository->getEffectiveAssignmentsForDate($dateStr)
            ->keyBy('employee_id');

        return $waiting->filter(function (int $employeeId) use ($date, $now, $assignments): bool {
            $assignment = $assignments->get($employeeId);

            if (! $assignment) {
                return false;
            }

            $deadline = $this->arrivalDeadline($date, $assignment);

            return $deadline === null || $now->lessThanOrEqualTo($deadline);
        })->values();
    }

    /**
     * The moment an employee without a punch turns from "awaiting arrival"
     * into "absent": their rotation's expected check-in plus grace minutes
     * (or the end of the day when the rotation carries no time schedule).
     *
     * Public so the daily operational report (DailyReportService) applies
     * the exact same deadline instead of drifting from smart absence.
     */
    public function arrivalDeadline(Carbon $date, mixed $assignment): ?Carbon
    {
        if (! $assignment) {
            return null;
        }

        $times = $this->rotationEngine->resolveTimes($assignment);
        $grace = (int) ($assignment->rotation->grace_minutes ?: $times['late_margin'] ?: 0);

        return $times['check_in']
            ? $date->copy()->setTimeFromTimeString($times['check_in'])->addMinutes($grace)
            : $date->copy()->endOfDay();
    }

    /**
     * Get the list of absent employees for a given date.
     *
     * @param  int|array<int, int>|null  $departmentIds
     * @param  int|array<int, int>|null  $rotationIds
     * @param  int|array<int, int>|null  $rotationGroupIds
     * @return Collection<int, int>
     */
    public function getAbsentEmployees(
        Carbon $date,
        int|array|null $departmentIds = null,
        int|array|null $rotationIds = null,
        int|array|null $rotationGroupIds = null,
    ): Collection {
        $expected = $this->getExpectedEmployees($date, $departmentIds, $rotationIds, $rotationGroupIds);

        if ($expected->isEmpty()) {
            return collect();
        }

        $dateStr = $date->toDateString();

        // Presence proof: a session with a real check-in, or a raw device
        // punch that is not merely yesterday's overnight checkout.
        // (Sessions without check_in_at and lone morning checkouts must not
        // mark the employee present — see computePresentIds().)
        $punchedIds = $this->computePresentIds($date, $expected);

        $absent = $expected->diff($punchedIds)->values();

        $onLeaveIds = UserVacationRequest::where('status', UserVacationRequest::STATUS_APPROVED)
            ->whereIn('user_id', $absent->toArray())
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->distinct()
            ->pluck('user_id');

        $interceptedIds = ShiftException::active()
            ->whereIn('employee_id', $absent->toArray())
            ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
            ->whereDate('from_date', '<=', $dateStr)
            ->whereDate('to_date', '>=', $dateStr)
            ->distinct()
            ->pluck('employee_id');

        $absent = $absent->diff($onLeaveIds)->diff($interceptedIds)->values();

        // A missing check-out from a previous day never excuses absence on a
        // NEW work day. Yesterday's still-open session is a separate, older
        // problem (surfaced by the daily report's missing check-out handling
        // and the dedicated missing-check-outs page); it says nothing about
        // whether the employee showed up today. An employee expected today
        // with no punch is absent today — the same rule as the operational
        // snapshot (dashboard), which never looks at previous days' sessions.

        // Official holidays excuse only the employees they actually cover: the
        // employee's rotation must not work on holidays AND the holiday must
        // apply to their branch/department (or to everyone). Recurring and
        // multi-day holidays are handled here too — matching the daily report
        // and the operational snapshot, instead of the old blanket "any
        // holiday cancels all absence" rule.
        if ($absent->isNotEmpty()) {
            $holidays = $this->activeHolidays();

            if ($holidays->isNotEmpty()) {
                $absentUsers = DB::table('users')
                    ->whereIn('id', $absent->toArray())
                    ->get(['id', 'branch_id', 'department_id'])
                    ->keyBy('id');

                $assignments = $this->rotationAssignmentRepository->getEffectiveAssignmentsForDate($dateStr)
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

        // A worker without a punch becomes absent only after their expected
        // check-in (including grace) has passed — the same "awaiting arrival"
        // rule as the operational snapshot. Before that deadline the day may
        // simply not have started for them yet (a late or overnight shift, or
        // a rotation with no time schedule), so the report must not flag them
        // as absent prematurely.
        if ($absent->isNotEmpty()) {
            $now = Carbon::now();
            $assignments = $this->rotationAssignmentRepository->getEffectiveAssignmentsForDate($dateStr)
                ->keyBy('employee_id');

            $absent = $absent->filter(function (int $employeeId) use ($date, $now, $assignments): bool {
                $assignment = $assignments->get($employeeId);

                if (! $assignment) {
                    return true;
                }

                $deadline = $this->arrivalDeadline($date, $assignment);

                return $deadline === null || $now->greaterThan($deadline);
            })->values();
        }

        return $absent;
    }

    /**
     * Classify every expected employee for the daily smart-absence report so
     * the UI can show exactly why each person is or is not absent.
     *
     * The buckets use the exact same rules as getAbsentEmployees() and the
     * operational snapshot, so the counts always reconcile: present + absent +
     * on_vacation + on_exception + holiday + awaiting_arrival equals the
     * number of expected employees. (The `incomplete` bucket is kept in the
     * payload for backward compatibility but is always zero: a missing
     * check-out from a previous day never excuses a NEW work day's absence.)
     *
     * @param  int|array<int, int>|null  $rotationIds
     * @param  int|array<int, int>|null  $rotationGroupIds
     * @return array{present: int, absent: int, on_vacation: int, on_exception: int, incomplete: int, holiday: int, awaiting_arrival: int}
     */
    public function getDailyStatusBreakdown(
        Carbon $date,
        ?int $departmentId = null,
        int|array|null $rotationIds = null,
        int|array|null $rotationGroupIds = null,
    ): array {
        $counts = [
            'present' => 0,
            'absent' => 0,
            'on_vacation' => 0,
            'on_exception' => 0,
            'incomplete' => 0,
            'holiday' => 0,
            'awaiting_arrival' => 0,
        ];

        $expected = $this->getExpectedEmployees($date, $departmentId, $rotationIds, $rotationGroupIds);

        if ($expected->isEmpty()) {
            return $counts;
        }

        $dateStr = $date->toDateString();
        $ids = $expected->toArray();

        // Physical presence: a session with a real check-in, or a raw device
        // punch that is not merely yesterday's overnight checkout (same rule
        // as getAbsentEmployees() so the counts always reconcile).
        $punchedIds = $this->computePresentIds($date, $expected);

        // Approved vacations and intercepting shift exceptions covering today.
        $vacationIds = UserVacationRequest::where('status', UserVacationRequest::STATUS_APPROVED)
            ->whereIn('user_id', $ids)
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->distinct()
            ->pluck('user_id');

        $exceptionIds = ShiftException::active()
            ->whereIn('employee_id', $ids)
            ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
            ->whereDate('from_date', '<=', $dateStr)
            ->whereDate('to_date', '>=', $dateStr)
            ->distinct()
            ->pluck('employee_id');

        // Awaiting arrival: expected, no punch, check-in deadline not passed yet.
        // (A missing check-out from a previous day never converts a new work
        // day into "incomplete" — same rule as getAbsentEmployees().)
        $assignments = $this->rotationAssignmentRepository->getEffectiveAssignmentsForDate($dateStr);
        $now = Carbon::now();
        $awaitingIds = collect();
        $deadlines = [];
        foreach ($assignments as $assignment) {
            if (! $expected->contains($assignment->employee_id)) {
                continue;
            }
            $deadline = $this->arrivalDeadline($date, $assignment);
            $deadlines[$assignment->employee_id] = $deadline;
            if ($deadline === null || $now->lte($deadline)) {
                $awaitingIds->push((int) $assignment->employee_id);
            }
        }

        // Official holidays excuse only the covered employees (same rule as
        // getAbsentEmployees).
        $holidays = $this->activeHolidays();
        $holidayExcusedIds = collect();
        if ($holidays->isNotEmpty()) {
            $users = DB::table('users')
                ->whereIn('id', $ids)
                ->get(['id', 'branch_id', 'department_id'])
                ->keyBy('id');
            $holidayExcusedIds = $expected->filter(function (int $employeeId) use ($holidays, $users, $assignments, $dateStr): bool {
                $employee = $users->get($employeeId);

                if (! $employee) {
                    return false;
                }

                if ((bool) ($assignments->firstWhere('employee_id', $employeeId)?->rotation?->work_on_holidays ?? false)) {
                    return false;
                }

                return $this->hasApplicableHoliday($holidays, $dateStr, $employee);
            });
        }

        foreach ($expected as $employeeId) {
            if ($punchedIds->contains($employeeId)) {
                $counts['present']++;
            } elseif ($vacationIds->contains($employeeId)) {
                $counts['on_vacation']++;
            } elseif ($exceptionIds->contains($employeeId)) {
                $counts['on_exception']++;
            } elseif ($holidayExcusedIds->contains($employeeId)) {
                $counts['holiday']++;
            } elseif ($awaitingIds->contains($employeeId)) {
                $counts['awaiting_arrival']++;
            } else {
                $counts['absent']++;
            }
        }

        return $counts;
    }

    /**
     * Determine absence days for a given employee in a specific month.
     *
     * Status values: present | absent | on_leave | holiday.
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
            ->first(['id', 'hire_date', 'branch_id', 'department_id', 'attendance_exemption_type', 'attendance_exemption_from', 'attendance_exemption_to']);

        if (! $employee) {
            return [];
        }

        // Historical assignment per day (one day back so yesterday's overnight
        // checkout can be recognised), not just the current one.
        $monthFromStr = $startOfMonth->toDateString();
        $monthToStr = $endOfMonth->toDateString();
        $monthAssignments = $this->rotationAssignmentRepository
            ->getEmployeeAssignmentsOverlapping(
                $employeeId,
                $startOfMonth->copy()->subDay()->toDateString(),
                $monthToStr
            );

        if ($monthAssignments->isEmpty()) {
            return [];
        }

        $pickAssignmentForDate = function (string $day) use ($monthAssignments): ?RotationAssignment {
            foreach ($monthAssignments as $candidate) {
                $start = $this->dateKey($candidate->start_date);
                $end = $candidate->end_date === null ? null : $this->dateKey($candidate->end_date);
                if ($start <= $day && ($end === null || $end >= $day)) {
                    return $candidate;
                }
            }

            return null;
        };

        $holidays = $this->activeHolidays();

        // 011/P1-E: batch the whole month up front (mirrors the proven
        // getMonthlyAbsenceReport() pattern) instead of ~4 exists() queries
        // per day (~120/month). Day membership below is logically identical
        // to the per-day queries it replaces (see evidence/p1e-batch-month).
        // Sessions only count with a real check-in; raw punches keep their
        // local times so an overnight checkout is not mistaken for arrival.
        $sessionDays = AttendanceSession::betweenDates($monthFromStr, $monthToStr)
            ->where('user_id', $employeeId)
            ->whereNotNull('check_in_at')
            ->distinct()
            ->pluck('attendance_date')
            ->map(fn ($value) => $this->dateKey($value))
            ->flip();

        $monthUtcBounds = [
            Carbon::parse($monthFromStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            Carbon::parse($monthToStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ];
        $rawTimesByDay = [];
        foreach (
            DB::table('raw_attendance_logs')
                ->where('user_id', $employeeId)
                ->whereBetween('punch_time', $monthUtcBounds)
                ->whereNull('deleted_at')
                ->pluck('punch_time') as $punch
        ) {
            $rawTimesByDay[$this->localDateFromUtc((string) $punch)][] = Carbon::parse((string) $punch, 'UTC')
                ->setTimezone(config('app.timezone'));
        }

        $monthVacations = $this->indexCoverage(
            UserVacationRequest::where('status', UserVacationRequest::STATUS_APPROVED)
                ->where('user_id', $employeeId)
                ->overlapping($monthFromStr, $monthToStr)
                ->get(['user_id', 'start_date', 'end_date'])
        );
        $monthExceptions = $this->indexCoverage(
            ShiftException::active()
                ->where('employee_id', $employeeId)
                ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
                ->whereDate('from_date', '<=', $monthToStr)
                ->whereDate('to_date', '>=', $monthFromStr)
                ->get(['employee_id', 'from_date', 'to_date', 'exception_type'])
        );

        $current = $startOfMonth->copy();
        while ($current->lte($endOfMonth)) {
            $dateStr = $current->toDateString();
            if ($this->isAttendanceExempt($employee, $dateStr)) {
                $current->addDay();

                continue;
            }

            if ($this->isNotYetHired($employee, $dateStr)) {
                $current->addDay();

                continue;
            }

            $dayAssignment = $pickAssignmentForDate($dateStr);

            if (! $dayAssignment) {
                $current->addDay();

                continue;
            }

            $rotation = $dayAssignment->rotation;
            $group = $dayAssignment->rotationGroup;
            $expectedTime = $this->rotationEngine->resolveTimes($dayAssignment)['check_in'] ?? null;

            $isExpected = $this->rotationEngine->isWorkDay($rotation, $group, $current);

            if ($isExpected) {
                // An official holiday excuses the employee only when their
                // rotation does not work on holidays and the holiday applies
                // to their branch/department (or to everyone) — the same rule
                // as the daily report and smart absence. Recurring and
                // multi-day holidays are covered by hasApplicableHoliday().
                $isHoliday = ! (bool) $rotation->work_on_holidays
                    && $this->hasApplicableHoliday($holidays, $dateStr, $employee);

                // A session with a real check-in proves presence. A raw punch
                // counts too — unless it is only yesterday's overnight
                // checkout landing on today's date.
                $hasPunch = isset($sessionDays[$dateStr]);

                if (! $hasPunch && isset($rawTimesByDay[$dateStr])) {
                    $prevAssignment = $pickAssignmentForDate($current->copy()->subDay()->toDateString());
                    $prevMap = collect($prevAssignment ? [$employeeId => $prevAssignment] : []);
                    $hasPunch = ! $this->isOvernightCheckoutOnly(
                        $employeeId,
                        $rawTimesByDay[$dateStr],
                        $current->copy(),
                        $prevMap
                    );
                }

                $approvedLeave = $this->isCoveredBy($employeeId, $dateStr, $monthVacations);

                $intercepted = $this->isCoveredBy($employeeId, $dateStr, $monthExceptions);

                $status = 'present';
                if ($approvedLeave || $intercepted) {
                    $status = 'on_leave';
                } elseif ($isHoliday) {
                    $status = 'holiday';
                } elseif (! $hasPunch) {
                    // A missing check-out from a previous day never excuses a
                    // NEW work day: no punch today means absent today (same
                    // rule as getAbsentEmployees() and the dashboard snapshot).
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

        // Historical assignments overlapping the range (one extra day back so
        // yesterday's overnight checkout can be recognised): each day uses
        // the assignment that was actually active on that day, so a rotation
        // transfer does not rewrite the past.
        $rangeAssignments = $this->rotationAssignmentRepository->getAssignmentsOverlapping(
            $from->copy()->subDay()->toDateString(),
            $toStr
        )->groupBy('employee_id');

        $pickAssignmentForDate = function (int $employeeId, string $day) use ($rangeAssignments): ?RotationAssignment {
            foreach ($rangeAssignments->get($employeeId, collect()) as $candidate) {
                $start = $this->dateKey($candidate->start_date);
                $end = $candidate->end_date === null ? null : $this->dateKey($candidate->end_date);
                if ($start <= $day && ($end === null || $end >= $day)) {
                    return $candidate;
                }
            }

            return null;
        };

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
            ->get(['id', 'hire_date', 'branch_id', 'department_id', 'termination_date', 'attendance_exemption_type', 'attendance_exemption_from', 'attendance_exemption_to'])
            ->keyBy('id');

        $activeIds = $activeUsers->keys()->all();

        // Attendance sessions grouped by date (one query for the whole range).
        // Only sessions with a real check-in prove presence — a checkout-only
        // row left over from a previous day says nothing about this day.
        $sessionsByDate = AttendanceSession::betweenDates($fromStr, $toStr)
            ->whereIn('user_id', $activeIds)
            ->whereNotNull('check_in_at')
            ->distinct()
            ->get(['attendance_date', 'user_id'])
            ->groupBy(fn ($row) => $this->dateKey($row->attendance_date))
            ->map(fn ($rows) => $rows->pluck('user_id')->flip());

        // Raw device punches are physical proof of presence too. The session
        // pipeline can attach a punch to a previous day's open session (early
        // morning punches outside the configured check-in window), leaving the
        // expected day without a session - without these, such employees would
        // be wrongly reported as absent. Times are kept (app timezone) so an
        // overnight duty's morning checkout is not mistaken for an arrival.
        $utcFrom = Carbon::parse($fromStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
        $utcTo = Carbon::parse($toStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
        $rawTimesByDate = [];
        foreach (
            DB::table('raw_attendance_logs')
                ->whereIn('user_id', $activeIds)
                ->whereBetween('punch_time', [$utcFrom, $utcTo])
                // Soft-deleted logs are excluded, exactly like the Eloquent model
                // query the rest of the report uses.
                ->whereNull('deleted_at')
                ->get(['punch_time', 'user_id']) as $rawRow
        ) {
            $localDay = $this->localDateFromUtc((string) $rawRow->punch_time);
            $rawTimesByDate[$localDay][(int) $rawRow->user_id][] = Carbon::parse((string) $rawRow->punch_time, 'UTC')
                ->setTimezone(config('app.timezone'));
        }

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
                ->whereDate('from_date', '<=', $toStr)
                ->whereDate('to_date', '>=', $fromStr)
                ->get(['employee_id', 'from_date', 'to_date', 'exception_type'])
        );

        // Official holidays inside the range cancel absence for the covered
        // employees — evaluated per employee below (rotation work_on_holidays
        // + branch/department scope, recurring and multi-day included), exactly
        // like the daily report.
        $holidays = $this->activeHolidays();

        $stats = []; // employee_id => ['expected' => int, 'present' => int, 'day_details' => array<int, array{date: string, status: string, label: string}>, 'absent_dates' => array<int, string>]
        $meta = [];  // employee_id => RotationAssignment (most recent in range)

        $current = $from->copy();
        while ($current->lte($to)) {
            $dateStr = $current->toDateString();
            $sessionThatDay = $sessionsByDate->get($dateStr, collect())->keys()->flip();
            $rawThatDay = $rawTimesByDate[$dateStr] ?? [];
            $prevStr = $current->copy()->subDay()->toDateString();

            foreach ($activeUsers as $employeeId => $employee) {
                $employeeId = (int) $employeeId;
                $assignment = $pickAssignmentForDate($employeeId, $dateStr);

                if (! $assignment) {
                    continue;
                }

                $rotation = $assignment->rotation;
                $group = $assignment->rotationGroup;

                // The employee must still be employed on this exact day, and
                // already hired (a newcomer is not expected before joining).
                $terminationDate = $employee->termination_date;
                if ($terminationDate !== null && $this->dateKey($terminationDate) < $dateStr) {
                    continue;
                }
                if ($this->isNotYetHired($employee, $dateStr)) {
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

                // A session with a real check-in proves presence. A raw punch
                // counts too — unless it is only yesterday's overnight
                // checkout landing on today's date.
                $hasPunch = $sessionThatDay->has($employeeId);

                if (! $hasPunch && isset($rawThatDay[$employeeId])) {
                    $prevAssignment = $pickAssignmentForDate($employeeId, $prevStr);
                    $prevMap = collect($prevAssignment ? [$employeeId => $prevAssignment] : []);
                    $hasPunch = ! $this->isOvernightCheckoutOnly(
                        $employeeId,
                        $rawThatDay[$employeeId],
                        $current->copy(),
                        $prevMap
                    );
                }

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
                    } else {
                        // A missing check-out from a previous day never excuses
                        // a NEW work day: no punch today means absent today
                        // (same rule as getAbsentEmployees()).
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
     * Whether the employee has any coverage range (vacation / exception)
     * containing the date — boolean twin of getCoverage() for call sites
     * that only need existence (011/P1-E batched month loop).
     *
     * @param  array<int, array<int, array{from: string, to: string}>>  $index
     */
    private function isCoveredBy(int $employeeId, string $dateStr, array $index): bool
    {
        foreach (($index[$employeeId] ?? []) as $range) {
            if ($range['from'] <= $dateStr && $range['to'] >= $dateStr) {
                return true;
            }
        }

        return false;
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
     * Exclude employees not hired yet on the given date.
     *
     * The hire_date column exists but was never consulted: a newcomer hired
     * mid-month was counted as expected (then absent) for days before they
     * even joined. A null hire_date means "unknown" and never excludes.
     */
    private function excludeNotYetHired($query, string $date): void
    {
        $query->where(function ($subQuery) use ($date): void {
            $subQuery->whereNull('hire_date')
                ->orWhere('hire_date', '<=', $date);
        });
    }

    /**
     * Employees with a genuine presence proof on a single day.
     *
     * Two accuracy rules live here:
     *   - an attendance session only counts when it carries a real check-in
     *     (a checkout-only row left over from a previous day proves nothing
     *     about today — mirrors the operational snapshot); and
     *   - a raw punch that is merely the morning checkout of yesterday's
     *     overnight duty does not count as today's arrival (see
     *     isOvernightCheckoutOnly()).
     *
     * @param  Collection<int, int>  $expected
     * @return Collection<int, int>
     */
    private function computePresentIds(Carbon $date, Collection $expected): Collection
    {
        $dateStr = $date->toDateString();
        $ids = $expected->toArray();

        if ($ids === []) {
            return collect();
        }

        $sessionIds = AttendanceSession::onDate($dateStr)
            ->whereIn('user_id', $ids)
            ->whereNotNull('check_in_at')
            ->distinct()
            ->pluck('user_id');

        $rawRows = RawAttendanceLog::query()
            ->whereIn('user_id', $ids)
            ->whereBetween('punch_time', $this->localDayUtcBounds($dateStr))
            ->get(['user_id', 'punch_time']);

        if ($rawRows->isEmpty()) {
            return $sessionIds->unique()->values();
        }

        $sessionFlip = $sessionIds->flip();
        $rawByUser = [];
        foreach ($rawRows as $row) {
            $rawByUser[(int) $row->user_id][] = Carbon::parse($row->punch_time, 'UTC')
                ->setTimezone(config('app.timezone'));
        }

        $assignments = $this->rotationAssignmentRepository
            ->getEffectiveAssignmentsForDate($dateStr)->keyBy('employee_id');
        $prevStr = $date->copy()->subDay()->toDateString();
        $prevAssignments = $prevStr === $dateStr
            ? $assignments
            : $this->rotationAssignmentRepository
                ->getEffectiveAssignmentsForDate($prevStr)->keyBy('employee_id');

        $present = $sessionIds->all();
        foreach ($rawByUser as $userId => $times) {
            if ($sessionFlip->has($userId)) {
                continue;
            }
            if ($this->isOvernightCheckoutOnly($userId, $times, $date, $prevAssignments)) {
                continue;
            }
            $present[] = $userId;
        }

        return collect($present)->unique()->values();
    }

    /**
     * Whether raw punches are only the checkout of yesterday's overnight duty.
     *
     * An overnight (multi-day) shift ending this morning leaves punches on
     * today's date that belong to yesterday's duty. When every punch of the
     * day falls inside the duty's departure-morning window and no session
     * with a check-in exists, the employee has not arrived for today.
     *
     * Public so the daily operational report (DailyReportService) applies
     * the exact same rule instead of drifting from smart absence.
     *
     * @param  array<int, Carbon>  $localTimes  Raw punch times in app timezone.
     */
    public function isOvernightCheckoutOnly(
        int $userId,
        array $localTimes,
        Carbon $date,
        Collection $prevAssignments,
    ): bool {
        $prev = $prevAssignments->get($userId);

        if (! $prev) {
            return false;
        }

        $prevDay = $date->copy()->subDay();
        if (! $this->rotationEngine->isWorkDay($prev->rotation, $prev->rotationGroup, $prevDay)) {
            return false;
        }

        $prevTimes = $this->rotationEngine->resolveTimes($prev);

        if (! ($prevTimes['is_overnight'] ?? false)) {
            return false;
        }

        $dayStr = $date->toDateString();
        $ahead = $prevTimes['next_day_out_ahead_margin'] ?? null;
        $above = $prevTimes['next_day_out_above_margin'] ?? null;

        if ($ahead || $above) {
            $windowStart = $ahead
                ? Carbon::parse("{$dayStr} {$ahead}")
                : $date->copy()->startOfDay();
            $windowEnd = $above
                ? Carbon::parse("{$dayStr} {$above}")
                : $date->copy()->endOfDay();
        } else {
            $checkOut = $prevTimes['check_out'] ?? null;

            if (! $checkOut) {
                return false;
            }

            // No explicit morning window: the checkout clusters around the
            // scheduled out_time, while a real next-day arrival is hours
            // away. Keep the window tight to avoid swallowing arrivals.
            $anchor = Carbon::parse("{$dayStr} {$checkOut}");
            $windowStart = $anchor->copy()->subMinutes(120);
            $windowEnd = $anchor->copy()->addMinutes(60);
        }

        if ($windowEnd->lt($windowStart)) {
            [$windowStart, $windowEnd] = [$windowEnd, $windowStart];
        }

        foreach ($localTimes as $time) {
            if ($time->lt($windowStart) || $time->gt($windowEnd)) {
                return false;
            }
        }

        return true;
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
     * Whether the employee had not been hired yet on a date (object twin of
     * excludeNotYetHired() for call sites holding a single employee row).
     */
    private function isNotYetHired(object $employee, string $date): bool
    {
        if (empty($employee->hire_date)) {
            return false;
        }

        return $this->dateKey($employee->hire_date) > $date;
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
     * Calculate monthly attendance for a single employee with weighted absence factor.
     *
     * Weighted logic: غياب يوم عمل واحد = cycle_length / work_days_count أيام تقويمية.
     * مثال: دورية 1 عمل / 3 راحة → cycle=4, work=1 → عامل الوزن 4 → غياب يوم واحد = 4 أيام وزناً.
     * دورية 2 عمل / 2 راحة → cycle=4, work=2 → عامل 2 → غياب يوم = يومين وزناً.
     * الأجازات المعتمدة تعتبر دوام (ليست غياب) وتُحتسب ضمن أيام العمل المنجزة.
     *
     * @return array{
     *     employee_id: int,
     *     month: int, year: int,
     *     from: string, to: string,
     *     rotation_id: ?int, rotation_name: ?string, rotation_group_name: ?string,
     *     cycle_length: ?int, work_days_count: ?int, rest_days_count: ?int,
     *     weight_factor: float,
     *     expected_physical: int,
     *     holiday_days: int,
     *     effective_expected: int,
     *     present_days: int,
     *     vacation_days: int,
     *     exception_days: int,
     *     absent_physical: int,
     *     absent_weighted: float,
     *     worked_physical: int,
     *     worked_weighted: float,
     *     attendance_rate: int,
     *     details: array<int, array{date:string, status:string, label:string, expected_time:?string, weight_factor:float, is_work_day:bool}>,
     *     has_assignment: bool,
     * }
     */
    public function getEmployeeMonthlyAttendance(int $employeeId, int $month, int $year): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $fromStr = $start->toDateString();
        $toStr = $end->toDateString();

        $employee = DB::table('users')
            ->where('id', $employeeId)
            ->whereNull('deleted_at')
            ->first(['id', 'name', 'employee_code', 'hire_date', 'branch_id', 'department_id', 'attendance_exemption_type', 'attendance_exemption_from', 'attendance_exemption_to', 'termination_date', 'status', 'is_active_employee']);

        if (! $employee) {
            return [
                'employee_id' => $employeeId, 'month' => $month, 'year' => $year,
                'from' => $fromStr, 'to' => $toStr,
                'rotation_id' => null, 'rotation_name' => null, 'rotation_group_name' => null,
                'cycle_length' => null, 'work_days_count' => null, 'rest_days_count' => null,
                'weight_factor' => 1,
                'expected_physical' => 0, 'holiday_days' => 0, 'effective_expected' => 0,
                'present_days' => 0, 'vacation_days' => 0, 'exception_days' => 0,
                'absent_physical' => 0, 'absent_weighted' => 0,
                'worked_physical' => 0, 'worked_weighted' => 0,
                'attendance_rate' => 0, 'details' => [], 'has_assignment' => false,
                'employee' => null,
            ];
        }

        $holidays = $this->activeHolidays();
        $details = [];
        $expectedPhysical = 0;
        $holidayDays = 0;
        $presentDays = 0;
        $vacationDays = 0;
        $exceptionDays = 0;
        $absentPhysical = 0;
        $absentWeighted = 0.0;
        $workedWeighted = 0.0;
        $hasAssignment = false;
        $primaryRotation = null;
        $primaryGroup = null;

        // Pre-fetch vacations and exceptions covering the month for this employee
        $vacationRanges = UserVacationRequest::where('status', UserVacationRequest::STATUS_APPROVED)
            ->where('user_id', $employeeId)
            ->whereDate('start_date', '<=', $toStr)
            ->whereDate('end_date', '>=', $fromStr)
            ->get(['start_date', 'end_date'])
            ->map(fn ($r) => ['from' => $this->dateKey($r->start_date), 'to' => $this->dateKey($r->end_date)])
            ->all();

        $exceptionRanges = ShiftException::active()
            ->where('employee_id', $employeeId)
            ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
            ->whereDate('from_date', '<=', $toStr)
            ->whereDate('to_date', '>=', $fromStr)
            ->get(['from_date', 'to_date', 'exception_type'])
            ->map(fn ($r) => ['from' => $this->dateKey($r->from_date), 'to' => $this->dateKey($r->to_date), 'type' => $r->exception_type])
            ->all();

        // 011/P1-G: batch the whole month (same proven pattern as
        // getMonthlyAbsence). Assignments are DATE columns, so the in-memory
        // pick below replicates getAssignmentForDate() exactly
        // (start<=day, open or end>=day, start desc, id desc, first).
        // One extra day back so yesterday's overnight checkout is recognised.
        $monthAssignments = $this->rotationAssignmentRepository
            ->getEmployeeAssignmentsOverlapping($employeeId, $start->copy()->subDay()->toDateString(), $toStr);

        $empSessionDays = AttendanceSession::betweenDates($fromStr, $toStr)
            ->where('user_id', $employeeId)
            ->whereNotNull('check_in_at')
            ->distinct()
            ->pluck('attendance_date')
            ->map(fn ($value) => $this->dateKey($value))
            ->flip();

        $empMonthUtcBounds = [
            Carbon::parse($fromStr)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            Carbon::parse($toStr)->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ];
        $empRawTimesByDay = [];
        foreach (
            DB::table('raw_attendance_logs')
                ->where('user_id', $employeeId)
                ->whereBetween('punch_time', $empMonthUtcBounds)
                ->whereNull('deleted_at')
                ->pluck('punch_time') as $empPunch
        ) {
            $empRawTimesByDay[$this->localDateFromUtc((string) $empPunch)][] = Carbon::parse((string) $empPunch, 'UTC')
                ->setTimezone(config('app.timezone'));
        }

        $pickAssignmentForDate = function (string $day) use ($monthAssignments): ?RotationAssignment {
            foreach ($monthAssignments as $candidate) {
                $start = $this->dateKey($candidate->start_date);
                $end = $candidate->end_date === null ? null : $this->dateKey($candidate->end_date);
                if ($start <= $day && ($end === null || $end >= $day)) {
                    return $candidate;
                }
            }

            return null;
        };

        $current = $start->copy();
        while ($current->lte($end)) {
            $dateStr = $current->toDateString();

            if ($this->isAttendanceExempt($employee, $dateStr)) {
                $current->addDay();

                continue;
            }

            $terminationDate = $employee->termination_date ? $this->dateKey($employee->termination_date) : null;
            if ($terminationDate !== null && $terminationDate < $dateStr) {
                $current->addDay();

                continue;
            }

            if ($this->isNotYetHired($employee, $dateStr)) {
                $current->addDay();

                continue;
            }

            $assignment = $pickAssignmentForDate($dateStr);
            if (! $assignment) {
                $current->addDay();

                continue;
            }
            $hasAssignment = true;
            $rotation = $assignment->rotation;
            $group = $assignment->rotationGroup;
            if (! $primaryRotation && $rotation) {
                $primaryRotation = $rotation;
                $primaryGroup = $group;
            }

            if (! $this->rotationEngine->isWorkDay($rotation, $group, $current)) {
                $current->addDay();

                continue;
            }

            $expectedPhysical++;
            $cycleLen = (int) ($rotation->cycle_length ?: 1);
            $workCount = (int) ($rotation->work_days_count ?: 1);
            $workCount = $workCount > 0 ? $workCount : 1;
            $weightFactor = $cycleLen / $workCount;

            $times = $this->rotationEngine->resolveTimes($assignment);
            $expectedTime = $times['check_in'] ?? null;

            $isHoliday = ! (bool) $rotation->work_on_holidays
                && $this->hasApplicableHoliday($holidays, $dateStr, $employee);

            if ($isHoliday) {
                $holidayDays++;
                $details[] = [
                    'date' => $dateStr,
                    'status' => 'holiday',
                    'label' => __('shifts::shifts.official_holiday'),
                    'expected_time' => $expectedTime,
                    'weight_factor' => $weightFactor,
                    'is_work_day' => true,
                ];
                $current->addDay();

                continue;
            }

            // A session with a real check-in proves presence. A raw punch
            // counts too — unless it is only yesterday's overnight checkout.
            $hasPunch = isset($empSessionDays[$dateStr]);
            if (! $hasPunch && isset($empRawTimesByDay[$dateStr])) {
                $prevAssignment = $pickAssignmentForDate($current->copy()->subDay()->toDateString());
                $prevMap = collect($prevAssignment ? [$employeeId => $prevAssignment] : []);
                $hasPunch = ! $this->isOvernightCheckoutOnly(
                    $employeeId,
                    $empRawTimesByDay[$dateStr],
                    $current->copy(),
                    $prevMap
                );
            }

            $onVacation = false;
            foreach ($vacationRanges as $range) {
                if ($range['from'] <= $dateStr && $range['to'] >= $dateStr) {
                    $onVacation = true;
                    break;
                }
            }
            $onException = null;
            foreach ($exceptionRanges as $range) {
                if ($range['from'] <= $dateStr && $range['to'] >= $dateStr) {
                    $onException = $range['type'];
                    break;
                }
            }

            if ($onVacation) {
                $vacationDays++;
                $workedWeighted += $weightFactor;
                $details[] = [
                    'date' => $dateStr,
                    'status' => 'vacation',
                    'label' => __('shifts::shifts.on_vacation'),
                    'expected_time' => $expectedTime,
                    'weight_factor' => $weightFactor,
                    'is_work_day' => true,
                ];
            } elseif ($onException !== null) {
                $exceptionDays++;
                $details[] = [
                    'date' => $dateStr,
                    'status' => 'exception',
                    'label' => $this->exceptionLabel($onException),
                    'expected_time' => $expectedTime,
                    'weight_factor' => $weightFactor,
                    'is_work_day' => true,
                ];
            } elseif ($hasPunch) {
                $presentDays++;
                $workedWeighted += $weightFactor;
                $details[] = [
                    'date' => $dateStr,
                    'status' => 'present',
                    'label' => __('shifts::shifts.present'),
                    'expected_time' => $expectedTime,
                    'weight_factor' => $weightFactor,
                    'is_work_day' => true,
                ];
            } else {
                $absentPhysical++;
                $absentWeighted += $weightFactor;
                $details[] = [
                    'date' => $dateStr,
                    'status' => 'absent',
                    'label' => __('shifts::shifts.absent_short'),
                    'expected_time' => $expectedTime,
                    'weight_factor' => $weightFactor,
                    'is_work_day' => true,
                ];
            }

            $current->addDay();
        }

        $effectiveExpected = $expectedPhysical - $holidayDays;
        $workedPhysical = $presentDays + $vacationDays;
        $attendanceRate = $effectiveExpected > 0 ? (int) round(($workedPhysical / $effectiveExpected) * 100) : 100;
        if ($attendanceRate > 100) {
            $attendanceRate = 100;
        }

        $primaryCycle = $primaryRotation ? (int) $primaryRotation->cycle_length : null;
        $primaryWork = $primaryRotation ? (int) $primaryRotation->work_days_count : null;
        $primaryRest = $primaryRotation ? (int) $primaryRotation->rest_days_count : null;
        $primaryWeight = ($primaryCycle && $primaryWork && $primaryWork > 0) ? round($primaryCycle / $primaryWork, 2) : 1;

        return [
            'employee_id' => $employeeId,
            'employee' => $employee,
            'month' => $month,
            'year' => $year,
            'from' => $fromStr,
            'to' => $toStr,
            'rotation_id' => $primaryRotation?->id,
            'rotation_name' => $primaryRotation?->name,
            'rotation_group_name' => $primaryGroup?->name,
            'cycle_length' => $primaryCycle,
            'work_days_count' => $primaryWork,
            'rest_days_count' => $primaryRest,
            'weight_factor' => $primaryWeight,
            'expected_physical' => $expectedPhysical,
            'holiday_days' => $holidayDays,
            'effective_expected' => $effectiveExpected,
            'present_days' => $presentDays,
            'vacation_days' => $vacationDays,
            'exception_days' => $exceptionDays,
            'absent_physical' => $absentPhysical,
            'absent_weighted' => round($absentWeighted, 2),
            'worked_physical' => $workedPhysical,
            'worked_weighted' => round($workedWeighted, 2),
            'attendance_rate' => $attendanceRate,
            'details' => $details,
            'has_assignment' => $hasAssignment,
        ];
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
