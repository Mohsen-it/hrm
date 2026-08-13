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
        $previousSessions = AttendanceSession::onDate($previousDate)->whereIn('user_id', $userIds)
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
        // Multi-day duty rotations can leave their (single) open session several
        // days behind the report date (e.g. 3-day duty). Track the most recent
        // open session strictly before the report day so those employees are
        // still caught once their departure morning has passed.
        $openBeforeReport = AttendanceSession::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->whereDate('attendance_date', '<', $date)
            ->orderByDesc('attendance_date')
            ->orderByDesc('check_in_at')
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $sessions) => $sessions->first());
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

        $rows = $users->map(function (User $user) use ($date, $cutoffTime, $expected, $assignments, $sessions, $previousSessions, $openBeforeReport, $previousExpected, $previousAssignments, $previousDate, $monthSessions, $monthlyAbsenceCounts, $monthlyVacationDays, $unregisteredFingerprintIds, $vacations, $exceptions, $rawPunchIds, $holidays): array {
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
            $expectedCheckOut = $this->sessionExpectedCheckOut($mainSession)
                ?? $this->expectedCheckOut($assignments->get($user->id));
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
            $currentDayIncomplete = ! $onMission && ! $onLeave && $mainSession !== null
                && $mainSession->check_out_at === null
                && $this->isIncompletePunchDue($date, $expectedCheckOut, $user->id, $expected->has($user->id), $assignment);

            $hasPreviousDayMissingCheckout = false;
            $previousCheckIn = null;
            $previousExpectedCheckOut = null;
            $previousAssignment = null;
            $previousUserSessions = $previousSessions->get($user->id, collect());
            $previousMainSession = $previousUserSessions->firstWhere(fn ($session) => $session->check_in_at !== null);
            if ($previousMainSession && $previousMainSession->check_out_at === null && $previousExpected->has($user->id)) {
                $previousAssignment = $previousAssignments->get($user->id);
                $previousExpectedCheckOut = $this->sessionExpectedCheckOut($previousMainSession)
                    ?? $this->expectedCheckOut($previousAssignment);
                if ($this->isIncompletePunchDue($previousDate, $previousExpectedCheckOut, $user->id, $previousExpected->has($user->id), $previousAssignment)) {
                    $hasPreviousDayMissingCheckout = true;
                    $previousCheckIn = $previousMainSession->check_in_at->format('H:i');
                }
            }

            // Duty rotations longer than one day leave their open session on an
            // earlier day than yesterday (3-day duty, single-session model). When
            // nothing was due from yesterday, evaluate the most recent open
            // session before the report day against its own departure window.
            if (! $hasPreviousDayMissingCheckout) {
                $olderOpen = $openBeforeReport->get($user->id);
                if ($olderOpen) {
                    $dueDate = $olderOpen->attendance_date?->toDateString() ?? $previousDate;
                    $dueAssignment = $dueDate === $previousDate
                        ? $previousAssignments->get($user->id)
                        : $this->rotationAssignmentRepository->getAssignmentForDate($user->id, $dueDate);
                    $dueRotation = $dueAssignment?->rotation;
                    $dueGroup = $dueAssignment?->rotationGroup;
                    $isExpectedDay = $dueDate === $previousDate
                        ? $previousExpected->has($user->id)
                        : ($dueRotation !== null && $dueGroup !== null
                            && $this->rotationEngine->isWorkDay($dueRotation, $dueGroup, $dueDate));
                    $dueExpectedCheckOut = $this->sessionExpectedCheckOut($olderOpen)
                        ?? $this->expectedCheckOut($dueAssignment);
                    if ($isExpectedDay && $this->isIncompletePunchDue($dueDate, $dueExpectedCheckOut, $user->id, true, $dueAssignment)) {
                        $hasPreviousDayMissingCheckout = true;
                        $previousCheckIn = $olderOpen->check_in_at?->format('H:i') ?? '';
                        $previousExpectedCheckOut = $dueExpectedCheckOut;
                        $previousAssignment = $dueAssignment;
                        $previousDate = $dueDate;
                    }
                }
            }

            $hasIncompletePunch = $currentDayIncomplete || $hasPreviousDayMissingCheckout;
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
                $exitNotes = [];
                if ($currentDayIncomplete) {
                    $windowEnd = $this->exitWindowEnd($date, $expectedCheckOut, $assignment)?->format('H:i');
                    $exitNotes[] = 'اليوم'.($windowEnd !== null ? ' (نافذة الخروج '.$windowEnd.')' : '');
                }
                if ($hasPreviousDayMissingCheckout) {
                    $windowEnd = $this->exitWindowEnd($previousDate, $previousExpectedCheckOut, $previousAssignment)?->format('H:i');
                    $label = $previousDate === Carbon::parse($date)->subDay()->toDateString() ? 'أمس' : $previousDate;
                    $exitNotes[] = $label.($windowEnd !== null ? ' (نافذة الخروج '.$windowEnd.')' : '');
                }
                $notes[] = 'لم يسجل خروج '.implode(' و ', $exitNotes);
            }
            if ($hasNoFingerprint) {
                $notes[] = 'الموظف غير مسجل في جهاز البصمة';
            }

            $checkIn = $first?->check_in_at?->format('H:i') ?? '';

            if (! $checkIn && $hasPreviousDayMissingCheckout) {
                $checkIn = $previousCheckIn ?? '';
            }

            return [
                'id' => $user->id, 'name' => $user->full_name, 'employee_code' => $user->employee_code,
                'department_name' => $user->department?->department_name ?? '—', 'rotation' => $rotation,
                'status' => $status, 'status_label' => $label,
                'check_in' => $checkIn, 'check_out' => $mainSession?->check_out_at?->format('H:i') ?? '',
                'expected' => $expected->has($user->id), 'expected_check_out' => $expectedCheckOut,
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

    /** Resolve the scheduled checkout time from the employee's active rotation. */
    private function expectedCheckOut(mixed $assignment): ?string
    {
        if (! $assignment) {
            return null;
        }

        return $this->rotationEngine->resolveTimes($assignment)['check_out'] ?? null;
    }

    /** Prefer the session's stored schedule because it reflects its actual work day. */
    private function sessionExpectedCheckOut(?AttendanceSession $session): ?string
    {
        if (! $session?->expected_check_out) {
            return null;
        }

        return substr((string) $session->expected_check_out, 0, 5);
    }

    /**
     * Resolve the end of the rotation's exit window for a report day.
     *
     * The exit window is sourced from the Time Schedule via RotationEngine::
     * resolveTimes() (single source of truth). Margins configured as minutes
     * are added to the scheduled check-out; legacy absolute window times
     * ("18:00:00") stored on the rotation are kept as-is for backward
     * compatibility. Rotations without a time schedule have no expected
     * check-out, but their legacy absolute window still applies on its own.
     *
     * For overnight duty rotations both the window end and the expected
     * check-out fall on the departure morning: the employee arrives on the
     * report day and leaves on the morning of the first rest day after their
     * duty block. The block length is encoded by the rotation pattern, so one
     * rule serves both 1-day duty ([1,0,…]) and 3-day duty ([1,1,1,0,…]).
     */
    private function exitWindowEnd(string $date, ?string $expectedCheckOut, mixed $assignment): ?Carbon
    {
        $isOvernight = $this->isAssignmentOvernight($assignment);

        if ($assignment) {
            $windowEndTime = $this->rotationEngine->resolveTimes($assignment)['out_above_margin'] ?? null;

            if ($windowEndTime) {
                $end = Carbon::parse($date.' '.substr((string) $windowEndTime, 0, 5));
                if ($isOvernight) {
                    $end = $this->moveWindowToDepartureDay($date, $end, $assignment);
                }

                return $end;
            }
        }

        if (! $expectedCheckOut) {
            // Without a window and without a check-out anchor there is
            // nothing to evaluate the violation against.
            return null;
        }

        // A zero margin closes the window exactly at the expected check-out;
        // rotations without a configured window deliberately get no grace.
        $marginMinutes = (int) ($assignment?->rotation?->timeSchedule?->out_above_margin ?? 0);

        $expectedOut = Carbon::parse($date.' '.$expectedCheckOut);
        if ($isOvernight) {
            $expectedOut = $this->moveWindowToDepartureDay($date, $expectedOut, $assignment);
        }

        return $expectedOut->addMinutes($marginMinutes);
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
     * A missing direction is a violation only once the rotation exit window
     * has ended. This prevents the report from flagging everyone who is still
     * at work inside their configured checkout window.
     *
     * Past days are still judged against their own exit window: an overnight
     * duty rotation closes its window on the departure morning (the first rest
     * day after the duty block), so an employee who checked in on an earlier
     * day is only listed once that morning window has passed — matching how the
     * report is used before the end of the shift. Days without a resolvable
     * window are always violations once they are in the past.
     */
    private function isIncompletePunchDue(string $date, ?string $expectedCheckOut, int $userId, bool $isExpectedDay, mixed $assignment): bool
    {
        if (! $isExpectedDay) {
            return false;
        }

        $reportDay = Carbon::parse($date)->startOfDay();
        if ($reportDay->gt(now()->startOfDay())) {
            return false;
        }

        $windowEnd = $this->exitWindowEnd($date, $expectedCheckOut, $assignment);
        if ($windowEnd === null) {
            // Without a resolvable window a past day is always a violation,
            // while the current day cannot be judged yet.
            return $reportDay->lt(now()->startOfDay());
        }

        return now()->gte($windowEnd);
    }
}
