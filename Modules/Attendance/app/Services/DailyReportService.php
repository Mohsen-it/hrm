<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\DailyAttendanceSummary;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Holidays\Models\Holiday;
use Modules\Shifts\Models\ShiftException;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Services\AbsenceCalculationService;
use Modules\Shifts\Services\RotationEngine;
use Modules\Users\Models\User;
use Modules\Vacations\Models\UserVacationRequest;

/** Builds the consolidated Arabic daily operational attendance report. */
class DailyReportService
{
    public function __construct(
        private AbsenceCalculationService $absenceService,
        private RotationAssignmentRepository $rotationAssignmentRepository,
        private RotationEngine $rotationEngine,
    ) {}

    /**
     * @return array{date:string, cutoff_time:string, rows:Collection, stats:array<string,int>}
     */
    public function build(
        string $date,
        string $cutoffTime,
        ?int $branchId = null,
        ?int $departmentId = null,
        ?int $userId = null,
        ?string $statusFilter = null,
    ): array {
        $day = Carbon::parse($date)->startOfDay();
        $date = $day->toDateString();
        $monthFrom = $day->copy()->startOfMonth()->toDateString();

        $users = User::query()->employees()->active()
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>=', $date))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($userId, fn ($q) => $q->whereKey($userId))
            ->with('department')
            ->orderBy('name')
            ->get();
        $userIds = $users->pluck('id');
        $expected = $this->absenceService->getExpectedEmployees($day, $departmentId)->flip();
        $assignments = $this->rotationAssignmentRepository->getAssignmentsForDate($date)
            ->whereIn('employee_id', $userIds)
            ->unique('employee_id')
            ->keyBy('employee_id');

        $previousDate = $day->copy()->subDay()->toDateString();
        $previousExpected = $this->absenceService->getExpectedEmployees($day->copy()->subDay(), $departmentId)->flip();
        $previousAssignments = $this->rotationAssignmentRepository->getAssignmentsForDate($previousDate)
            ->whereIn('employee_id', $userIds)
            ->unique('employee_id')
            ->keyBy('employee_id');

        $sessions = AttendanceSession::onDate($date)->whereIn('user_id', $userIds)
            ->orderBy('check_in_at')->get()->groupBy('user_id');

        // A raw device punch is physical proof of presence even when the
        // session pipeline could not create a session for this date (e.g. an
        // early-morning punch outside the configured check-in window). The
        // smart-absence report already treats raw punches as presence, so the
        // daily report must never mark these employees absent.
        $rawPunchIds = RawAttendanceLog::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('punch_time', $this->localDayUtcBounds($date))
            ->distinct()
            ->pluck('user_id')
            ->flip();
        // Active official holidays, checked per employee (branch/department
        // scoping + work_on_holidays) using the same rule as smart absence.
        $holidays = Holiday::active()->get();
        // The missing-checkout table is a "اليوم السابق" snapshot: it only ever
        // looks at the previous day's open sessions, never at older duties or
        // the report day itself.
        $previousSessions = AttendanceSession::onDate($previousDate)->whereIn('user_id', $userIds)
            ->orderBy('check_in_at')->get()->groupBy('user_id');
        $monthSessions = AttendanceSession::betweenDates($monthFrom, $date)
            ->whereIn('user_id', $userIds)->whereNotNull('check_in_at')
            ->orderBy('check_in_at')->get()->groupBy('user_id');
        // Count only the preceding days; the selected report day is added below
        // whenever the employee is absent, even if its daily summary is not yet rebuilt.
        $monthlyAbsenceCounts = DailyAttendanceSummary::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'absent')
            ->whereDate('summary_date', '>=', $monthFrom)
            ->whereDate('summary_date', '<', $date)
            ->selectRaw('user_id, COUNT(DISTINCT DATE(summary_date)) as absence_count')
            ->groupBy('user_id')
            ->pluck('absence_count', 'user_id');
        // Use the same source as the "Unregistered Employees" fingerprint
        // page so this report cannot silently omit employees without templates.
        $unregisteredFingerprintIds = User::query()
            ->whereKey($userIds)
            ->withoutSuperAdmin()
            ->whereDoesntHave('fingerprintTemplates')
            ->pluck('id')
            ->flip();
        $vacations = UserVacationRequest::approved()->whereIn('user_id', $userIds)
            ->overlapping($date, $date)->with('vacationType')->get()->keyBy('user_id');
        $monthlyVacationDays = UserVacationRequest::approved()->whereIn('user_id', $userIds)
            ->overlapping($monthFrom, $date)
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $requests) => $requests->sum(
                fn (UserVacationRequest $request) => $this->daysOverlappingPeriod($request, $monthFrom, $date)
            ));
        $exceptions = ShiftException::active()->whereIn('employee_id', $userIds)
            ->whereIn('exception_type', ['leave', 'mission', 'training', 'swap'])
            ->overlapping($date)->get()->groupBy('employee_id');

        $rows = $users->map(function (User $user) use ($date, $cutoffTime, $expected, $assignments, $sessions, $previousSessions, $previousExpected, $previousAssignments, $previousDate, $monthSessions, $monthlyAbsenceCounts, $monthlyVacationDays, $unregisteredFingerprintIds, $vacations, $exceptions, $rawPunchIds, $holidays): array {
            $userSessions = $sessions->get($user->id, collect());
            $first = $userSessions->first();
            $assignment = $assignments->get($user->id);
            $rotation = $assignment?->rotation?->name
                ? $assignment->rotation->name.($assignment->rotationGroup?->name ? ' ('.$assignment->rotationGroup->name.')' : '')
                : '';
            $vacation = $vacations->get($user->id);
            $exception = $exceptions->get($user->id, collect())->first();
            // The main shift session is the first session of the day that
            // recorded a check-in. A later session (overtime, a second visit
            // after the exit window) must never turn an already-recorded
            // check-out into a missing-checkout violation.
            $mainSession = $userSessions->firstWhere(fn ($session) => $session->check_in_at !== null);
            $onMission = $exception?->exception_type === 'mission' || $this->isMission($vacation);
            // The vacations table must reflect only the employees who are
            // genuinely on vacation on the report day. An approved vacation
            // only matters on a day the employee was expected to work: on one
            // of their rotation rest days (e.g. a 1-work / 3-rest pattern) the
            // vacation is meaningless and they must stay in the "rest" group
            // instead. Someone who attended work (recorded a check-in) is
            // classified by their actual attendance rather than listed as on
            // leave.
            $onLeave = $expected->has($user->id)
                && ($vacation !== null || in_array($exception?->exception_type, ['leave', 'training', 'swap'], true))
                && ! $userSessions->contains(fn ($session) => $session->check_in_at !== null);
            $late = $first?->check_in_at && $first->check_in_at->format('H:i') > $cutoffTime;
            $hasNoFingerprint = $unregisteredFingerprintIds->has($user->id);

            $hasPreviousDayMissingCheckout = false;
            $previousCheckIn = null;
            $previousAssignment = null;
            $previousFlaggedSession = null;
            $previousUserSessions = $previousSessions->get($user->id, collect());
            // Prefer the session that actually recorded a check-out: a real
            // exit punch anywhere on the duty day proves the employee left
            // (an earlier stray open session — e.g. a mid-night punch — must
            // never turn a registered exit into a missing-checkout violation).
            // Only when NO check-out was recorded do we evaluate the main
            // open session.
            $previousMainSession = $previousUserSessions->firstWhere(fn ($session) => $session->check_out_at !== null)
                ?? $previousUserSessions->firstWhere(fn ($session) => $session->check_in_at !== null);
            if ($previousMainSession && $previousMainSession->check_out_at === null && $previousExpected->has($user->id)) {
                $previousAssignment = $previousAssignments->get($user->id);
                if ($this->isIncompletePunchDue($previousDate, $date, $previousMainSession, $previousExpected->has($user->id), $previousAssignment)) {
                    $hasPreviousDayMissingCheckout = true;
                    $previousCheckIn = $previousMainSession->check_in_at->format('H:i');
                    $previousFlaggedSession = $previousMainSession;
                }
            }

            $hasIncompletePunch = $hasPreviousDayMissingCheckout;

            // Expected entry/exit times per the rotation's time table (جدول
            // الوقت), taken from the duty that is actually missing its exit so
            // the report's columns always match the flagged session.
            $flaggedSession = $hasPreviousDayMissingCheckout ? $previousFlaggedSession : $mainSession;
            $flaggedAssignment = $hasPreviousDayMissingCheckout ? $previousAssignment : $assignment;
            $expectations = $this->scheduleExpectations($flaggedAssignment, $flaggedSession);
            $rowExpectedCheckIn = $expectations['check_in'];
            $rowExpectedCheckOut = $expectations['check_out'];
            $rowExpectedCheckOutNextDay = $expectations['is_multi_day'];

            $lateCount = $monthSessions->get($user->id, collect())->filter(
                fn ($s) => $s->check_in_at && $s->check_in_at->format('H:i') > $cutoffTime
            )->unique(fn ($s) => $s->attendance_date?->toDateString())->count();

            // An official holiday only excuses employees whose rotation does
            // not work on holidays — matching smart absence. Employees who
            // actually attended keep their real status.
            $isHoliday = ! $userSessions->count()
                && $this->isOfficialHoliday($date, $user, $holidays, $assignment);
            $hasRawPunch = ! $userSessions->count() && $rawPunchIds->has($user->id);

            $status = 'present';
            $label = 'حاضر';
            if ($onMission) {
                $status = 'mission';
                $label = 'مهمة سفر';
            } elseif ($onLeave) {
                $status = 'leave';
                $label = 'إجازة';
            } elseif (! $expected->has($user->id) && ! $hasPreviousDayMissingCheckout) {
                $status = 'rest';
                $label = 'غير متوقع دوامه';
            } elseif ($isHoliday && ! $hasPreviousDayMissingCheckout) {
                $status = 'holiday';
                $label = 'إجازة رسمية';
            } elseif (! $userSessions->count() && ! $hasPreviousDayMissingCheckout && ! $hasRawPunch) {
                $status = 'absent';
                $label = 'غياب';
            } elseif ($hasIncompletePunch) {
                $status = 'incomplete';
                $label = 'دخول دون خروج';
            } elseif ($late) {
                $status = 'late';
                $label = 'متأخر';
            }

            $notes = [];
            if ($status === 'late') {
                $notes[] = 'عدد مرات التأخر خلال الشهر: '.$this->arabicNumber($lateCount);
            }
            if ($status === 'absent') {
                $absenceCount = (int) ($monthlyAbsenceCounts->get($user->id, 0)) + 1;
                $notes[] = 'عدد أيام الغياب خلال الشهر: '.$this->arabicNumber($absenceCount);
            }
            if ($status === 'leave' && $vacation !== null) {
                $leaveDays = (int) $monthlyVacationDays->get($user->id, 0);
                $notes[] = 'عدد أيام الإجازة خلال الشهر: '.$this->arabicNumber($leaveDays);
            }
            if ($hasRawPunch) {
                $notes[] = 'بصمة مسجلة دون جلسة';
            }
            if ($hasIncompletePunch) {
                $notes[] = 'لم يسجل خروج أمس';
            }
            if ($hasNoFingerprint) {
                $notes[] = 'الموظف غير مسجل في جهاز البصمة';
            }

            $checkIn = $first?->check_in_at?->format('H:i') ?? '';

            if ($hasPreviousDayMissingCheckout) {
                $checkIn = $previousCheckIn ?? '';
            }

            return [
                'id' => $user->id, 'name' => $user->full_name, 'employee_code' => $user->employee_code,
                'department_name' => $user->department?->department_name ?? '—', 'rotation' => $rotation,
                'status' => $status, 'status_label' => $label,
                'check_in' => $checkIn, 'check_out' => $mainSession?->check_out_at?->format('H:i') ?? '',
                'expected' => $expected->has($user->id),
                'expected_check_in' => $rowExpectedCheckIn, 'expected_check_out' => $rowExpectedCheckOut,
                'expected_check_out_next_day' => $rowExpectedCheckOutNextDay,
                'has_no_fingerprint' => $hasNoFingerprint, 'has_incomplete_punch' => $hasIncompletePunch,
                'late_minutes' => $late && $first?->check_in_at ? $first->check_in_at->diffInMinutes(Carbon::parse($date.' '.$cutoffTime)) : 0,
                'notes' => implode('، ', $notes),
            ];
        })->when($statusFilter, function (Collection $collection) use ($statusFilter): Collection {
            return match ($statusFilter) {
                'no_fingerprint' => $collection->where('has_no_fingerprint', true)
                    ->map(fn (array $row) => [
                        ...$row,
                        'status' => 'no_fingerprint',
                        'status_label' => 'لا توجد بصمة مسجلة',
                    ]),
                'incomplete' => $collection->where('has_incomplete_punch', true),
                default => $collection->where('status', $statusFilter),
            };
        })->values();

        $stats = $rows->countBy('status')->all();
        $stats['no_fingerprint'] = $rows->where('has_no_fingerprint', true)->count();
        $stats['incomplete'] = $rows->where('has_incomplete_punch', true)->count();
        $stats['total'] = $rows->count();

        return ['date' => $date, 'cutoff_time' => $cutoffTime, 'rows' => $rows, 'stats' => $stats];
    }

    private function isMission(?UserVacationRequest $request): bool
    {
        $type = $request?->vacationType;
        if (! $type) {
            return false;
        }

        return str_contains(mb_strtolower(($type->code ?? '').' '.($type->name_ar ?? '').' '.($type->name_en ?? '')), 'مهم')
            || str_contains(mb_strtolower(($type->code ?? '').' '.($type->name_ar ?? '').' '.($type->name_en ?? '')), 'mission')
            || str_contains(mb_strtolower(($type->code ?? '').' '.($type->name_ar ?? '').' '.($type->name_en ?? '')), 'travel');
    }

    /** Count the inclusive vacation days that fall within a report period. */
    private function daysOverlappingPeriod(UserVacationRequest $request, string $from, string $to): int
    {
        $periodStart = Carbon::parse($from)->startOfDay();
        $periodEnd = Carbon::parse($to)->startOfDay();
        $start = Carbon::parse($request->start_date)->startOfDay();
        $end = Carbon::parse($request->end_date)->startOfDay();

        if ($start->lt($periodStart)) {
            $start = $periodStart;
        }
        if ($end->gt($periodEnd)) {
            $end = $periodEnd;
        }

        return $start->gt($end) ? 0 : (int) $start->diffInDays($end) + 1;
    }

    /** Format a count as an Arabic numeral and keep it in RTL text order. */
    private function arabicNumber(int $number): string
    {
        return "\u{200F}".strtr((string) $number, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ]);
    }

    /** Resolve the scheduled check-in time from the employee's active rotation. */
    private function expectedCheckIn(mixed $assignment): ?string
    {
        if (! $assignment) {
            return null;
        }

        return $this->rotationEngine->resolveTimes($assignment)['check_in'] ?? null;
    }

    /** Resolve the scheduled checkout time from the employee's active rotation. */
    private function expectedCheckOut(mixed $assignment): ?string
    {
        if (! $assignment) {
            return null;
        }

        return $this->rotationEngine->resolveTimes($assignment)['check_out'] ?? null;
    }

    /** Prefer the session's stored schedule because it reflects its actual work day. */
    private function sessionExpectedCheckIn(?AttendanceSession $session): ?string
    {
        if (! $session?->expected_check_in) {
            return null;
        }

        return substr((string) $session->expected_check_in, 0, 5);
    }

    /** Prefer the session's stored schedule because it reflects its actual work day. */
    private function sessionExpectedCheckOut(?AttendanceSession $session): ?string
    {
        if (! $session?->expected_check_out) {
            return null;
        }

        return substr((string) $session->expected_check_out, 0, 5);
    }

    /** Format a time-schedule column (string or Carbon) as H:i. */
    private function formatScheduleTime(mixed $time): ?string
    {
        if (! $time) {
            return null;
        }

        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        $time = (string) $time;

        return preg_match('/^(\d{2}:\d{2})/', $time, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * The current entry/exit times of the rotation's linked time table (جدول
     * الوقت) — the schedules configured on the Time Schedules page. Sessions
     * created before a schedule change keep their old times, so the report
     * deliberately reads the live schedule as the authority.
     *
     * @return array{check_in: ?string, check_out: ?string, is_multi_day: bool, out_above_margin: int}
     */
    private function liveScheduleTimes(mixed $assignment): array
    {
        $schedule = $assignment?->rotation?->timeSchedule;
        if (! $schedule) {
            return ['check_in' => null, 'check_out' => null, 'is_multi_day' => false, 'out_above_margin' => 0];
        }

        return [
            'check_in' => $this->formatScheduleTime($schedule->in_time),
            'check_out' => $this->formatScheduleTime($schedule->out_time),
            'is_multi_day' => (bool) $schedule->is_multi_day,
            'out_above_margin' => (int) ($schedule->out_above_margin ?? 0),
        ];
    }

    /**
     * The expected entry/exit times for one duty, straight from the rotation's
     * current time table. Rotations without a linked schedule fall back to the
     * session's stored values (which mirror the schedule that was live on the
     * duty day).
     *
     * @return array{check_in: ?string, check_out: ?string, is_multi_day: bool, out_above_margin: int}
     */
    private function scheduleExpectations(mixed $assignment, ?AttendanceSession $session): array
    {
        $live = $this->liveScheduleTimes($assignment);
        if ($live['check_in'] !== null && $live['check_out'] !== null) {
            return $live;
        }

        return [
            'check_in' => $this->sessionExpectedCheckIn($session) ?? $this->expectedCheckIn($assignment),
            'check_out' => $this->sessionExpectedCheckOut($session) ?? $this->expectedCheckOut($assignment),
            'is_multi_day' => $this->isAssignmentOvernight($assignment),
            'out_above_margin' => $live['out_above_margin'],
        ];
    }

    /**
     * Resolve the end of the exit deadline for a duty day.
     *
     * The deadline comes from the rotation's TIME TABLE (جدول الوقت), not
     * from the rotation's absolute punch window: the expected check-out time
     * from the live schedule plus the schedule's "out_above_margin" grace
     * minutes. This is what the user expects a 1-3 duty rotation to obey —
     * check in on the duty day, leave on the second day at the scheduled
     * out_time plus the margin (e.g. 08:00 next day + 120 minutes = 10:00).
     * The rotation's own out_ahead_margin / out_above_margin fields only
     * describe the physical punch-classification window (بداية/نهاية نافذة
     * الخروج) and are NOT a reliable deadline: e.g. a legacy "23:59" end would
     * delay the report by a whole day.
     *
     * Rotations without a time schedule have no expected check-out, so their
     * absolute exit window still applies on its own.
     *
     * For overnight duty rotations both the deadline and the expected
     * check-out fall on the departure morning: the employee arrives on the
     * duty day and leaves on the morning of the first rest day after their
     * duty block. The block length is encoded by the rotation pattern, so one
     * rule serves both 1-day duty ([1,0,…]) and 3-day duty ([1,1,1,0,…]).
     */
    private function exitWindowEnd(string $date, mixed $assignment, ?AttendanceSession $session = null): ?Carbon
    {
        $rotation = $assignment?->rotation;
        $times = $this->scheduleExpectations($assignment, $session);

        if ($times['check_out']) {
            // Time-table deadline: scheduled out_time + the schedule's grace
            // minutes (a zero margin closes it exactly at the check-out).
            $expectedOut = Carbon::parse($date.' '.$times['check_out']);
            if ($times['is_multi_day']) {
                $expectedOut = $this->moveWindowToDepartureDay($date, $expectedOut, $assignment);
            }

            return $expectedOut->addMinutes($times['out_above_margin']);
        }

        // Rotations without a time schedule have no expected check-out, but
        // their absolute exit window still applies on its own.
        $windowEndTime = $rotation?->out_above_margin;
        if ($windowEndTime) {
            $end = Carbon::parse($date.' '.substr((string) $windowEndTime, 0, 8));
            if ($this->isAssignmentOvernight($assignment)) {
                $end = $this->moveWindowToDepartureDay($date, $end, $assignment);
            }

            return $end;
        }

        // Without a time table and without a window there is nothing to
        // evaluate the violation against.
        return null;
    }

    /**
     * Move an exit-window time onto the morning the employee actually leaves.
     *
     * Overnight duty employees check in on the report day and leave on the
     * morning of the first rest day after their duty block (1-day or 3-day
     * duty, both encoded by the rotation pattern). The wall-clock time of the
     * window is kept and only the calendar day moves, so a morning margin
     * yields a morning window on the departure day. Assignments that resolve
     * no group keep the historical "next calendar day" behavior.
     */
    private function moveWindowToDepartureDay(string $date, Carbon $window, mixed $assignment): Carbon
    {
        $rotation = $assignment?->rotation;
        $group = $assignment?->rotationGroup;

        if ($rotation && $group) {
            $departure = $this->rotationEngine->getNextRestDay($rotation, $group, $date);

            return Carbon::create(
                $departure->year,
                $departure->month,
                $departure->day,
                $window->hour,
                $window->minute,
                $window->second,
            );
        }

        return $window->addDay();
    }

    /**
     * Whether the report date is an official holiday for this employee.
     *
     * Mirrors the smart-absence rule (AbsenceCalculationService): a holiday
     * excuses the employee only when their rotation does not work on
     * holidays, and only when the holiday applies to their branch/department
     * (or to everyone). Multi-day holidays are covered via duration_days.
     *
     * @param  Collection<int, Holiday>  $holidays
     */
    private function isOfficialHoliday(string $date, User $user, Collection $holidays, mixed $assignment): bool
    {
        if ((bool) ($assignment?->rotation?->work_on_holidays ?? false)) {
            return false;
        }

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
                || in_array((int) $user->branch_id, $holiday->applies_to_branches ?? [], true)
                || in_array((int) $user->department_id, $holiday->applies_to_departments ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * UTC boundary strings covering one full app-timezone day.
     *
     * Raw device punches are stored in UTC while report dates are local, so
     * matching a local date requires shifting the day's bounds to UTC.
     *
     * @return array{0: string, 1: string}
     */
    private function localDayUtcBounds(string $date): array
    {
        $day = Carbon::parse($date);

        return [
            $day->copy()->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'),
            $day->copy()->endOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Whether the employee's rotation expects the check-out on the next day
     * (a multi-day / overnight schedule). This mirrors the flag the attendance
     * pipeline uses when it builds the session's expected check-out, so the
     * report never disagrees with the punch-classification logic.
     */
    private function isAssignmentOvernight(mixed $assignment): bool
    {
        if (! $assignment) {
            return false;
        }

        $times = $this->rotationEngine->resolveTimes($assignment);

        return (bool) ($times['is_overnight'] ?? false);
    }

    /**
     * A missing direction is a violation only once the exit deadline has
     * ended. This prevents the report from flagging everyone who is still at
     * work inside their scheduled checkout window.
     *
     * The violation is strictly limited to the previous day (اليوم السابق):
     * only an open session whose duty day is the day before the report is
     * evaluated — never the report day's own duty and never older sessions,
     * so each report shows exactly yesterday's missed check-outs. An overnight
     * duty closes its deadline on the departure morning (the first rest day
     * after the duty block), so a 1-3 employee who checked in yesterday is
     * listed once that morning deadline has passed.
     */
    private function isIncompletePunchDue(
        string $dutyDate,
        string $reportDate,
        ?AttendanceSession $session,
        bool $isExpectedDay,
        mixed $assignment,
    ): bool {
        if (! $isExpectedDay) {
            return false;
        }

        $reportDay = Carbon::parse($reportDate)->startOfDay();
        $sessionDay = Carbon::parse($dutyDate)->startOfDay();

        // The table is strictly a "اليوم السابق" snapshot: only the previous
        // day's open sessions are evaluated, never the report day's own duty
        // and never older stale sessions (the date notes those produced are
        // gone).
        if (! $sessionDay->eq($reportDay->copy()->subDay())) {
            return false;
        }

        // Never judge a duty from the future.
        if ($sessionDay->gt(now()->startOfDay())) {
            return false;
        }

        $windowEnd = $this->exitWindowEnd($dutyDate, $assignment, $session);
        if ($windowEnd === null) {
            // Without a resolvable deadline yesterday's duty is a violation.
            return true;
        }

        return now()->gte($windowEnd);
    }
}
