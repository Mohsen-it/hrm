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
     * @param  int|array<int, int>|null  $departmentIds
     * @return array{date:string, cutoff_time:string, rows:Collection, stats:array<string,int>}
     */
    public function build(
        string $date,
        string $cutoffTime,
        ?int $branchId = null,
        int|array|null $departmentIds = null,
        ?int $userId = null,
        ?string $statusFilter = null,
    ): array {
        $day = Carbon::parse($date)->startOfDay();
        $date = $day->toDateString();
        $monthFrom = $day->copy()->startOfMonth()->toDateString();
        $departmentIds = $departmentIds === null || is_array($departmentIds)
            ? ($departmentIds ?? [])
            : [$departmentIds];

        $users = User::query()->employees()->active()
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>=', $date))
            // Same employment boundaries as smart absence: nobody is
            // expected (or absent) before being hired, and approved HR
            // attendance-exemptions remove the employee from the report.
            ->where(fn ($q) => $q->whereNull('hire_date')->orWhere('hire_date', '<=', $date))
            ->where(fn ($q) => $q->whereNull('attendance_exemption_type')
                ->orWhereNull('attendance_exemption_from')
                ->orWhere('attendance_exemption_from', '>', $date)
                ->orWhere('attendance_exemption_to', '<', $date))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($departmentIds !== [], fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->when($userId, fn ($q) => $q->whereKey($userId))
            ->with('department')
            ->orderBy('name')
            ->get();
        $userIds = $users->pluck('id');
        $expected = $this->absenceService->getExpectedEmployees($day, $departmentIds)->flip();
        $assignments = $this->rotationAssignmentRepository->getAssignmentsForDate($date)
            ->whereIn('employee_id', $userIds)
            ->unique('employee_id')
            ->keyBy('employee_id');

        $previousDate = $day->copy()->subDay()->toDateString();
        $previousExpected = $this->absenceService->getExpectedEmployees($day->copy()->subDay(), $departmentIds)->flip();
        $previousAssignments = $this->rotationAssignmentRepository->getAssignmentsForDate($previousDate)
            ->whereIn('employee_id', $userIds)
            ->unique('employee_id')
            ->keyBy('employee_id');

        $sessions = AttendanceSession::onDate($date)->whereIn('user_id', $userIds)
            ->orderBy('check_in_at')->get()->groupBy('user_id');

        // A raw device punch is physical proof of presence even when the
        // session pipeline could not create a session for this date (e.g. an
        // early-morning punch outside the configured check-in window). Times
        // are kept (app timezone) so an overnight duty's morning checkout is
        // not mistaken for an arrival — the exact smart-absence rule.
        $rawTimesByUser = RawAttendanceLog::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('punch_time', $this->localDayBounds($date))
            ->get(['user_id', 'punch_time'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows
                ->map(fn ($row) => $this->toLocalTime($row->punch_time))
                ->all());
        $rawPunchIds = $rawTimesByUser->keys()->flip();
        // Previous-day raw punches (app timezone): back the evening-punch
        // obligation of overnight duties and the "بصمة مسجلة دون جلسة" cases.
        $prevRawTimesByUser = RawAttendanceLog::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('punch_time', $this->localDayBounds($previousDate))
            ->get(['user_id', 'punch_time'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows
                ->map(fn ($row) => $this->toLocalTime($row->punch_time))
                ->all());
        // Approved leaves / shift exceptions covering the previous day: they
        // excuse the evening-punch obligation and add context to the
        // missing-checkout note instead of silently hiding it.
        $prevVacations = UserVacationRequest::approved()->whereIn('user_id', $userIds)
            ->overlapping($previousDate, $previousDate)->get()->keyBy('user_id');
        $prevExceptions = ShiftException::active()->whereIn('employee_id', $userIds)
            ->whereIn('exception_type', ['leave', 'mission', 'training', 'swap'])
            ->overlapping($previousDate)->get()->groupBy('employee_id');
        // Employees still inside their arrival window (expected, no proof of
        // presence, check-in deadline not passed): the report shows them as
        // awaiting instead of falsely flagging them absent on current-day
        // mornings. Same rule as smart absence, narrowed to this roster.
        $awaitingIds = $this->absenceService->getAwaitingArrivalEmployees($day, $departmentIds)
            ->intersect($userIds)
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
        // Only rotation WORK days count: a stale summary that marks a rest day
        // as absent/vacation (e.g. a leave recorded on a Friday-Saturday rest)
        // must never inflate the monthly counter. Each absent date is verified
        // against the historically active assignment via RotationEngine.
        $monthAssignments = $this->rotationAssignmentRepository->getAssignmentsOverlapping($monthFrom, $date)
            ->whereIn('employee_id', $userIds->all())
            ->groupBy('employee_id');
        $assignmentForDate = function (int $employeeId, string $day) use ($monthAssignments): mixed {
            foreach ($monthAssignments->get($employeeId, collect()) as $candidate) {
                $start = substr((string) $candidate->start_date, 0, 10);
                $end = $candidate->end_date === null ? null : substr((string) $candidate->end_date, 0, 10);
                if ($start <= $day && ($end === null || $end >= $day)) {
                    return $candidate;
                }
            }

            return null;
        };
        $isWorkDayOn = function (int $employeeId, string $day) use ($assignmentForDate): bool {
            $candidate = $assignmentForDate($employeeId, $day);
            if (! $candidate || ! $candidate->rotation || ! $candidate->rotationGroup) {
                return false;
            }

            return $this->rotationEngine->isWorkDay($candidate->rotation, $candidate->rotationGroup, $day);
        };
        $monthlyAbsentDates = DailyAttendanceSummary::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'absent')
            ->whereDate('summary_date', '>=', $monthFrom)
            ->whereDate('summary_date', '<', $date)
            ->get(['user_id', 'summary_date'])
            ->groupBy('user_id');
        $monthlyAbsenceCounts = $monthlyAbsentDates->map(
            fn (Collection $rows, int $userId) => $rows
                ->map(fn ($row) => substr((string) $row->summary_date, 0, 10))
                ->unique()
                ->filter(fn (string $day) => $isWorkDayOn((int) $userId, $day))
                ->count()
        );
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
            ->map(fn (Collection $requests, int $userId) => $requests->sum(
                fn (UserVacationRequest $request) => $this->workDaysOverlappingPeriod($request, $monthFrom, $date, (int) $userId, $isWorkDayOn)
            ));
        $exceptions = ShiftException::active()->whereIn('employee_id', $userIds)
            ->whereIn('exception_type', ['leave', 'mission', 'training', 'swap'])
            ->overlapping($date)->get()->groupBy('employee_id');

        $rows = $users->map(function (User $user) use ($date, $day, $cutoffTime, $expected, $assignments, $sessions, $previousSessions, $previousExpected, $previousAssignments, $previousDate, $monthSessions, $monthlyAbsenceCounts, $monthlyVacationDays, $unregisteredFingerprintIds, $vacations, $exceptions, $rawTimesByUser, $prevRawTimesByUser, $prevVacations, $prevExceptions, $awaitingIds, $holidays, $assignmentForDate): array {
            $userSessions = $sessions->get($user->id, collect());
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
            // Presence requires a real check-in: a checkout-only session left
            // over from a previous day proves nothing about today (same rule
            // as smart absence). A raw punch counts too — unless it is only
            // yesterday's overnight checkout landing on today's date.
            $hasCheckedIn = $mainSession !== null;
            $rawTimes = $rawTimesByUser->get($user->id, []);
            $hasRawPunch = ! $hasCheckedIn && $rawTimes !== []
                && ! $this->absenceService->isOvernightCheckoutOnly($user->id, $rawTimes, $day->copy(), $previousAssignments);
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
                && ! $hasCheckedIn;
            // Lateness is judged against the stricter of the report's cutoff
            // and the employee's own arrival deadline (check-in + grace): a
            // 10:00 shift arriving at 09:30 is on time for its rotation even
            // though it is past a 09:00 cutoff, while an 08:00 shift keeps the
            // cutoff strictness. Same deadline smart absence uses.
            $personalDeadline = $this->absenceService->arrivalDeadline($day, $assignment)?->format('H:i');
            $lateThreshold = $personalDeadline !== null && $personalDeadline > $cutoffTime ? $personalDeadline : $cutoffTime;
            $late = $mainSession?->check_in_at && $mainSession->check_in_at->format('H:i') > $lateThreshold;
            $hasNoFingerprint = $unregisteredFingerprintIds->has($user->id);

            $hasPreviousDayMissingCheckout = false;
            $hasMissingEveningPunch = false;
            $previousAssignment = null;
            $previousFlaggedSession = null;
            $previousUserSessions = $previousSessions->get($user->id, collect());
            // Previous-day punch times (app timezone) shared by the checkout
            // and evening evaluations below.
            $prevDayTimes = collect($prevRawTimesByUser->get($user->id, []))
                ->map(fn (Carbon $t) => $t->format('H:i'))->sort()->values();
            // Prefer the session that actually recorded a check-out: a real
            // exit punch anywhere on the duty day proves the employee left
            // (an earlier stray open session — e.g. a mid-night punch — must
            // never turn a registered exit into a missing-checkout violation).
            // Only when NO check-out was recorded do we evaluate the main
            // open session.
            $previousMainSession = $previousUserSessions->firstWhere(fn ($session) => $session->check_out_at !== null)
                ?? $previousUserSessions->firstWhere(fn ($session) => $session->check_in_at !== null);
            if ($previousMainSession && $previousExpected->has($user->id)) {
                $previousAssignment = $previousAssignments->get($user->id);                // The checkout table is for FINAL checkouts only: the same-day
                // exit of a day duty, or the departure-morning exit of an
                // overnight duty's last block day. A missed scheduled checkout
                // on a mid-block overnight day (day 1-2 of 3-9, day 1-6 of
                // 7-21) belongs to the evening table instead — one violation,
                // one message.
                $prevIsFinalDuty = ! $this->isAssignmentOvernight($previousAssignment)
                    || $this->isLastBlockDay($previousAssignment, $previousDate);
                $checkoutMiss = false;
                if ($previousMainSession->check_out_at === null) {
                    $checkoutMiss = $this->isIncompletePunchDue($previousDate, $date, $previousMainSession, $previousExpected->has($user->id), $previousAssignment);
                } elseif ($this->isHealedMissingCheckout($previousMainSession, $previousAssignment)) {
                    // The session looks closed, but no checkout was recorded
                    // on the duty day itself: it was either auto-closed by the
                    // nightly job (fabricated checkout) or closed by a later
                    // day's punch. Both hide a forgotten exit punch.
                    $checkoutMiss = true;
                }
                if ($checkoutMiss && $prevIsFinalDuty) {
                    $hasPreviousDayMissingCheckout = true;
                    $previousFlaggedSession = $previousMainSession;
                } elseif ($checkoutMiss) {
                    // Mid-block miss diverted to the evening table: the
                    // employee showed up but recorded no usable checkout for
                    // that duty day — AND recorded no evening punch either. A
                    // recorded evening punch (even one that opened its own
                    // session, later auto-closed) fulfils the evening side,
                    // so nothing is flagged.
                    $hasMissingEveningPunch = ($previousMainSession->check_in_at !== null || $prevDayTimes->isNotEmpty())
                        && ! $prevDayTimes->contains(fn (string $t) => $this->isEveningPresence($t, $previousAssignment));
                }
            }

            $hasIncompletePunch = $hasPreviousDayMissingCheckout;

            // Evening-punch obligation (overnight duties only: 1-3, 3-9, 7-21,
            // 4-12): every expected work day requires a presence punch inside
            // the schedule's explicit third-punch window when the time table
            // configures one, otherwise after the entry window closes.
            // Evaluated on the previous day, like missing checkouts. Excused
            // on leave/mission days and on official holidays, and skipped
            // when the employee never showed up at all (absence already
            // covers that), when the duty is already flagged for its missing
            // checkout, or when a mid-block miss was diverted above (one
            // violation, one message).
            $prevAssignment = $previousAssignments->get($user->id);
            $prevVacation = $prevVacations->get($user->id);
            $prevException = $prevExceptions->get($user->id, collect())->first();
            $prevExcused = $prevVacation !== null
                || $prevException !== null
                || $this->isOfficialHoliday($previousDate, $user, $holidays, $prevAssignment);
            if ($previousExpected->has($user->id) && ! $prevExcused && $this->isAssignmentOvernight($prevAssignment)) {
                $prevHasPresence = $prevDayTimes->isNotEmpty()
                    || $previousUserSessions->firstWhere(fn ($session) => $session->check_in_at !== null) !== null;
                $hasEvening = $prevDayTimes->contains(
                    fn (string $t) => $this->isEveningPresence($t, $prevAssignment)
                );
                if (! $hasMissingEveningPunch) {
                    $hasMissingEveningPunch = $prevHasPresence && ! $hasEvening && ! $hasPreviousDayMissingCheckout;
                }
            }

            // Expected entry/exit times per the rotation's time table (جدول
            // الوقت), taken from the duty that is actually missing its exit so
            // the report's columns always match the flagged session.
            $flaggedSession = $hasPreviousDayMissingCheckout ? $previousFlaggedSession : $mainSession;
            $flaggedAssignment = $hasPreviousDayMissingCheckout ? $previousAssignment : $assignment;
            $expectations = $this->scheduleExpectations($flaggedAssignment, $flaggedSession);
            $rowExpectedCheckIn = $expectations['check_in'];
            $rowExpectedCheckOut = $expectations['check_out'];
            $rowExpectedCheckOutNextDay = $expectations['is_multi_day'];

            // Previous duty's genuine checkout that landed before the
            // expected end but inside the schedule's tolerated early-leave
            // minutes: not a violation, yet reviewers must tell it apart
            // from a full checkout (e.g. a 07:51 departure for an 08:00
            // overnight duty). Auto-closed fabrications never qualify, and
            // mid-block overnight days keep their evening reading instead.
            $prevEarlyExitNote = null;
            if ($previousMainSession?->check_out_at !== null
                && $previousAssignment
                && $previousExpected->has($user->id)
                && ! (is_string($previousMainSession->notes) && str_contains($previousMainSession->notes, 'أغلق تلقائياً'))
                && (! $this->isAssignmentOvernight($previousAssignment) || $this->isLastBlockDay($previousAssignment, $previousDate))) {
                $prevExpectations = $this->scheduleExpectations($previousAssignment, $previousMainSession);
                $prevEarlyExitNote = $this->earlyExitWithinToleranceNote(
                    $previousMainSession->check_out_at,
                    $prevExpectations['check_out'] ?? null,
                    $previousDate,
                    $previousAssignment,
                    (bool) ($prevExpectations['is_multi_day'] ?? false),
                    ' أمس ('.$day->copy()->subDay()->format('d-m').')'
                );
            }

            $lateCount = $monthSessions->get($user->id, collect())->filter(
                function ($s) use ($cutoffTime, $assignmentForDate, $user): bool {
                    if (! $s->check_in_at) {
                        return false;
                    }
                    $dayStr = $s->attendance_date?->toDateString();
                    if (! $dayStr) {
                        return false;
                    }
                    // Same threshold the status itself uses: the stricter of
                    // the report cutoff and the employee's own arrival
                    // deadline for that day (check-in + grace).
                    $threshold = $cutoffTime;
                    $dayDeadline = $this->absenceService
                        ->arrivalDeadline(Carbon::parse($dayStr), $assignmentForDate((int) $user->id, $dayStr))
                        ?->format('H:i');
                    if ($dayDeadline !== null && $dayDeadline > $threshold) {
                        $threshold = $dayDeadline;
                    }

                    return $s->check_in_at->format('H:i') > $threshold;
                }
            )->unique(fn ($s) => $s->attendance_date?->toDateString())->count();

            // Same-day genuine checkout before the expected end but inside
            // the tolerated early-leave minutes (day duties only — overnight
            // checkouts land on the next day and are read on the previous
            // duty above instead).
            $earlyExitNote = null;
            if ($mainSession?->check_out_at !== null && $assignment && ! $this->isAssignmentOvernight($assignment) && $expected->has($user->id)) {
                $mainExpectations = $this->scheduleExpectations($assignment, $mainSession);
                $earlyExitNote = $this->earlyExitWithinToleranceNote(
                    $mainSession->check_out_at, $mainExpectations['check_out'] ?? null, $date, $assignment, false, ''
                );
            }

            // An official holiday only excuses employees whose rotation does
            // not work on holidays — matching smart absence. Employees who
            // actually attended keep their real status.
            $isHoliday = ! $hasCheckedIn
                && $this->isOfficialHoliday($date, $user, $holidays, $assignment);
            $isAwaiting = $awaitingIds->has($user->id);

            $status = 'present';
            $label = 'حاضر';
            // The status always describes the REPORT day itself: today's
            // sessions decide present/late/absent. Yesterday's missing
            // check-out stays visible through has_incomplete_punch and the
            // "لم يسجل خروج أمس" note (plus its own DOCX table) but must
            // never hide today's attendance — previously an employee absent
            // today vanished from the غياب table while employees present
            // today were shown with yesterday's check-in time.
            if ($onMission) {
                $status = 'mission';
                $label = 'مهمة سفر';
            } elseif ($onLeave) {
                $status = 'leave';
                $label = 'إجازة';
            } elseif (! $assignment) {
                // No rotation assignment at all: the employee punches outside
                // every roster (never expected, never absent). This is NOT a
                // rest day — it means HR has not assigned them a rotation.
                $status = 'unassigned';
                $label = 'بلا إسناد دورية';
            } elseif (! $expected->has($user->id)) {
                $status = 'rest';
                $label = 'غير متوقع دوامه';
            } elseif ($isHoliday) {
                $status = 'holiday';
                $label = 'إجازة رسمية';
            } elseif ($isAwaiting) {
                $status = 'awaiting';
                $label = 'بانتظار الوصول';
            } elseif (! $hasCheckedIn && ! $hasRawPunch) {
                $status = 'absent';
                $label = 'غياب';
            } elseif ($late) {
                $status = 'late';
                $label = 'متأخر';
            }

            $notes = [];
            if ($status === 'late') {
                $notes[] = 'عدد مرات التأخر خلال الشهر: '.$this->arabicNumber($lateCount);
            }
            if ($status === 'awaiting') {
                $notes[] = 'بانتظار الوصول — الدوام المتوقع: '.($rowExpectedCheckIn ?? '—');
            }
            if ($status === 'absent') {
                $absenceCount = (int) ($monthlyAbsenceCounts->get($user->id, 0)) + 1;
                $notes[] = 'عدد أيام الغياب خلال الشهر: '.$this->arabicNumber($absenceCount);
            }
            if ($status === 'leave' && $vacation !== null) {
                $leaveDays = (int) $monthlyVacationDays->get($user->id, 0);
                $notes[] = 'عدد أيام الإجازة خلال الشهر: '.$this->arabicNumber($leaveDays);
            }
            if ($earlyExitNote !== null) {
                $notes[] = $earlyExitNote;
            }
            if ($prevEarlyExitNote !== null) {
                $notes[] = $prevEarlyExitNote;
            }
            if ($hasRawPunch) {
                $notes[] = 'بصمة مسجلة دون جلسة';
            }
            if ($hasIncompletePunch) {
                // The date is explicit (not just "أمس") so the row stays
                // unambiguous next to report-day columns like the evening
                // punch, which describe a different calendar day.
                $notes[] = 'لم يسجل خروج أمس ('.$day->copy()->subDay()->format('d-m').')';
                // A flagged violation on a day covered by an approved leave
                // or exception (e.g. retroactive sick leave with punches)
                // confuses reviewers: name the context without hiding the
                // violation — the punches were really recorded.
                if ($prevVacation !== null || $prevException !== null) {
                    $notes[] = 'تنبيه: يوجد إجازة أو استثناء بتاريخ الدوام';
                }
            }
            if ($hasMissingEveningPunch) {
                $notes[] = 'لم يسجل البصمة المسائية أمس ('.$day->copy()->subDay()->format('d-m').')';
            }
            if ($hasNoFingerprint) {
                $notes[] = 'الموظف غير مسجل في جهاز البصمة';
            }

            // Actual punches of the PREVIOUS duty day (the day the
            // missing-checkout and missing-evening tables describe): the real
            // check-in from the flagged session, the real checkout only when
            // one was genuinely recorded on the duty day itself (auto-closed
            // fabrications and later-day punches written back are excluded),
            // and the last raw device punch of that day (an early exit that
            // the pipeline never counted as a checkout still shows up here).
            $prevCheckIn = $previousMainSession?->check_in_at?->format('H:i') ?? '';
            $prevCheckOutRecorded = null;
            if ($previousMainSession?->check_out_at !== null
                && $previousMainSession->check_out_at->toDateString() === $previousDate
                && ! (is_string($previousMainSession->notes) && str_contains($previousMainSession->notes, 'أغلق تلقائياً'))) {
                $prevCheckOutRecorded = $previousMainSession->check_out_at->format('H:i');
            }
            $prevLastPunch = $prevDayTimes->isNotEmpty() ? $prevDayTimes->last() : null;
            // Always today's punch: yesterday's check-in belongs to the
            // missing-checkout table (actual entry/exit columns), never to
            // this column. The main session (first one with a check-in) is
            // used so a stray checkout-only row cannot mask a real arrival.
            $checkIn = $mainSession?->check_in_at?->format('H:i') ?? '';
            $checkOutAt = $mainSession?->check_out_at;
            // An overnight checkout lands on the next calendar day: flag it so
            // the UI can mark it (+1) instead of showing a time that looks
            // earlier than the check-in.
            $checkOutNextDay = $checkOutAt !== null
                && $mainSession?->attendance_date !== null
                && $checkOutAt->toDateString() > $mainSession->attendance_date->toDateString();
            // The evening presence punch of overnight duties (24h shifts): the
            // latest punch inside the explicit third-punch window when the
            // time table configures one, otherwise the latest punch after
            // the entry window closes — excluding the check-in and, when the
            // checkout itself lands the same evening (mid-block days), the
            // checkout, which already has its own column. Display only, it
            // never changes check-in/check-out or the status. Day duties
            // never carry one.
            $dayPunchTimes = collect($rawTimes)->map(fn (Carbon $t) => $t->format('H:i'))->sort()->values();
            $checkOutSameDay = $checkOutAt !== null && ! $checkOutNextDay ? $checkOutAt->format('H:i') : null;
            $eveningPunch = null;
            if ($this->isAssignmentOvernight($assignment)) {
                $thirdWindow = $this->thirdPunchWindow($assignment);
                $eveningPunch = $dayPunchTimes
                    ->filter(fn (string $t) => $t !== $checkIn && $t !== $checkOutSameDay)
                    ->filter(fn (string $t) => $thirdWindow !== null
                        ? $this->isThirdPunch($t, $thirdWindow)
                        : $t > $this->eveningThreshold($assignment))
                    ->last();
            }

            return [
                'id' => $user->id, 'name' => $user->full_name, 'employee_code' => $user->employee_code,
                'department_name' => $user->department?->department_name ?? '—', 'rotation' => $rotation,
                'status' => $status, 'status_label' => $label,
                'check_in' => $checkIn, 'check_out' => $checkOutAt?->format('H:i') ?? '',
                'check_out_next_day' => $checkOutNextDay,
                'evening_punch' => $eveningPunch, 'punch_times' => $dayPunchTimes->all(),
                'expected' => $expected->has($user->id),
                'expected_check_in' => $rowExpectedCheckIn, 'expected_check_out' => $rowExpectedCheckOut,
                'expected_check_out_next_day' => $rowExpectedCheckOutNextDay,
                // Previous duty day actuals for the DOCX missing-checkout
                // (entry + exit) and missing-evening (last punch) tables.
                // prev_check_out is a genuinely recorded exit on the duty day;
                // when empty the tables fall back to prev_last_punch (an early
                // exit punch the pipeline never counted as a checkout).
                'prev_check_in' => $prevCheckIn, 'prev_check_out' => $prevCheckOutRecorded ?? '',
                'prev_last_punch' => $prevLastPunch,
                'has_no_fingerprint' => $hasNoFingerprint, 'has_incomplete_punch' => $hasIncompletePunch,
                'has_missing_evening_punch' => $hasMissingEveningPunch,
                // Absolute diff from the lateness threshold to the actual
                // check-in (Carbon 3 returns signed diffs by default, which
                // previously rendered every late_minutes value negative).
                'late_minutes' => $late && $mainSession?->check_in_at ? (int) Carbon::parse($date.' '.$lateThreshold)->diffInMinutes($mainSession->check_in_at, true) : 0,
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
                'evening' => $collection->where('has_missing_evening_punch', true),
                // Employees whose fingerprint is not enrolled on the device
                // can never punch, so they would sit in the غياب table every
                // day as noise: they belong to the "عدم تسجيل البصمة على
                // الجهاز" table instead and are hidden from the غياب filter.
                'absent' => $collection->where('status', 'absent')->where('has_no_fingerprint', false),
                'awaiting' => $collection->where('status', 'awaiting'),
                'unassigned' => $collection->where('status', 'unassigned'),
                default => $collection->where('status', $statusFilter),
            };
        })->values();

        $stats = $rows->countBy('status')->all();
        // Keep the غياب counter in sync with the غياب table: unregistered
        // employees are listed under "عدم تسجيل البصمة على الجهاز", not here.
        $stats['absent'] = $rows->where('status', 'absent')->where('has_no_fingerprint', false)->count();
        $stats['awaiting'] = $rows->where('status', 'awaiting')->count();
        $stats['no_fingerprint'] = $rows->where('has_no_fingerprint', true)->count();
        $stats['incomplete'] = $rows->where('has_incomplete_punch', true)->count();
        $stats['evening'] = $rows->where('has_missing_evening_punch', true)->count();
        $stats['unassigned'] = $rows->where('status', 'unassigned')->count();
        $stats['rest'] = $rows->where('status', 'rest')->count();
        $stats['holiday'] = $rows->where('status', 'holiday')->count();
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

    /**
     * Count only the rotation WORK days of a vacation within a report period.
     *
     * A leave recorded on a rest day (e.g. a Friday-Saturday rest covered by a
     * mission) is meaningless and must not inflate "عدد أيام الإجازة خلال
     * الشهر" — same rule as the daily status itself.
     *
     * @param  callable(int, string):bool  $isWorkDayOn
     */
    private function workDaysOverlappingPeriod(UserVacationRequest $request, string $from, string $to, int $userId, callable $isWorkDayOn): int
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

        if ($start->gt($end)) {
            return 0;
        }

        $count = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($isWorkDayOn($userId, $cursor->toDateString())) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    /** Format a count as an Arabic numeral and keep it in RTL text order. */
    private function arabicNumber(int $number): string
    {
        return "\u{200F}".strtr((string) $number, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ]);
    }

    /**
     * Note distinguishing an early checkout inside the schedule's tolerated
     * early-leave minutes from a full checkout — e.g. a 07:51 departure for
     * an 08:00 duty with early_margin 30. Returns null for full (or late)
     * checkouts and for early checkouts beyond the tolerance (those stay a
     * pipeline-level early-leave, not a tolerance case).
     */
    private function earlyExitWithinToleranceNote(
        ?Carbon $checkoutAt,
        ?string $expectedCheckout,
        string $dutyDate,
        mixed $assignment,
        bool $shiftToDeparture,
        string $suffix,
    ): ?string {
        if ($checkoutAt === null || $expectedCheckout === null || ! $assignment) {
            return null;
        }

        $expectedEnd = Carbon::parse($dutyDate.' '.$expectedCheckout);
        if ($shiftToDeparture) {
            $expectedEnd = $this->moveWindowToDepartureDay($dutyDate, $expectedEnd, $assignment);
        }

        $earlyBy = (int) $checkoutAt->diffInMinutes($expectedEnd, false);
        if ($earlyBy <= 0) {
            return null;
        }

        $margin = (int) ($this->rotationEngine->resolveTimes($assignment)['early_margin'] ?? 0);
        if ($earlyBy > max(0, $margin)) {
            return null;
        }

        return 'خروج مبكر ضمن السماحية'.$suffix.' (قبل الموعد بـ '.$this->arabicNumber($earlyBy).')';
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
                // Mid-block duty days (day 1-2 of 3-9, day 1-6 of 7-21) close
                // their own session with the same-evening checkout punch, so
                // their deadline is the end of the rotation's same-day exit
                // window — not the departure morning. Only the last day of
                // the block waits for the departure-morning checkout.
                if (! $this->isLastBlockDay($assignment, $date)) {
                    $sameDayEnd = $this->sameDayExitWindowEnd($date, $assignment);

                    if ($sameDayEnd !== null) {
                        return $sameDayEnd;
                    }
                }
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
            } elseif ($rotation?->out_ahead_margin
                && Carbon::parse($date.' '.substr((string) $rotation->out_ahead_margin, 0, 5))->gt($end)) {
                // An inverted window (end time earlier than the start time)
                // wraps past midnight: the exit is due on the next morning,
                // never on the duty day itself.
                $end = $end->addDay();
            }

            return $end;
        }

        // Without a time table and without a window there is nothing to
        // evaluate the violation against.
        return null;
    }

    /**
     * Whether the report date is the last work day of the employee's duty block.
     *
     * Dates without a resolvable rotation/group keep the historical behavior
     * (departure-morning deadline).
     */
    private function isLastBlockDay(mixed $assignment, string $date): bool
    {
        $rotation = $assignment?->rotation;
        $group = $assignment?->rotationGroup;

        if (! $rotation || ! $group) {
            return true;
        }

        return $this->rotationEngine->isLastWorkDayOfBlock($rotation, $group, $date);
    }

    /**
     * End of the rotation's same-day exit window for a duty date.
     *
     * Read from the resolved absolute punch-window edges (legacy rotation
     * window times win over schedule margins — same source the punch
     * classifier uses). An inverted window (end earlier than start) wraps
     * past midnight. Returns null when the rotation has no same-day exit
     * window, in which case the caller falls back to the departure-morning
     * deadline.
     */
    private function sameDayExitWindowEnd(string $date, mixed $assignment): ?Carbon
    {
        if (! $assignment) {
            return null;
        }

        $times = $this->rotationEngine->resolveTimes($assignment);
        $end = $times['out_above_margin'] ?? null;
        if (! is_string($end) || preg_match('/^(\d{2}:\d{2})/', $end, $matches) !== 1) {
            return null;
        }

        $endDt = Carbon::parse($date.' '.$matches[1]);
        $start = $times['out_ahead_margin'] ?? null;
        if (is_string($start) && preg_match('/^(\d{2}:\d{2})/', $start, $startMatches) === 1
            && Carbon::parse($date.' '.$startMatches[1])->gt($endDt)) {
            $endDt = $endDt->addDay();
        }

        return $endDt;
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
     * End of the entry window (H:i) of an overnight duty: an evening presence
     * punch must fall after it, so a very late check-in is never mistaken
     * for the evening punch. Falls back to noon when the rotation carries no
     * entry window.
     */
    private function eveningThreshold(mixed $assignment): string
    {
        if ($assignment) {
            $times = $this->rotationEngine->resolveTimes($assignment);
            $end = $times['in_above_margin'] ?? null;
            if (is_string($end) && preg_match('/^(\d{2}:\d{2})/', $end, $matches) === 1) {
                return $matches[1];
            }
        }

        return '12:00';
    }

    /**
     * The explicit third (evening) punch window of the assignment's time
     * table, when the schedule configures one. Only overnight duties ever
     * carry it — day schedules return null even when set.
     *
     * @return array{start: string, end: string}|null
     */
    private function thirdPunchWindow(mixed $assignment): ?array
    {
        if (! $assignment) {
            return null;
        }

        $times = $this->rotationEngine->resolveTimes($assignment);
        $start = $times['third_punch_start'] ?? null;
        $end = $times['third_punch_end'] ?? null;
        if (! is_string($start) || $start === '' || ! is_string($end) || $end === '') {
            return null;
        }

        return ['start' => substr($start, 0, 5), 'end' => substr($end, 0, 5)];
    }

    /**
     * Whether an H:i punch falls inside the explicit third-punch window
     * (inclusive on both edges).
     *
     * @param  array{start: string, end: string}  $window
     */
    private function isThirdPunch(string $time, array $window): bool
    {
        if ($window['end'] >= $window['start']) {
            return $time >= $window['start'] && $time <= $window['end'];
        }

        return $time >= $window['start'] || $time <= $window['end'];
    }

    /**
     * Whether a punch counts as evening presence: the explicit third-punch
     * window wins when the time table configures one, otherwise the legacy
     * rule (any punch after the entry window closes) applies.
     */
    private function isEveningPresence(string $time, mixed $assignment): bool
    {
        $window = $this->thirdPunchWindow($assignment);
        if ($window !== null) {
            return $this->isThirdPunch($time, $window);
        }

        return $time > $this->eveningThreshold($assignment);
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
     * Boundary strings covering one full roster day.
     *
     * Raw device punches are stored as naive local wall time (devices push
     * wall-clock time and the server shares the same clock — verified: DB
     * NOW() == app now() == punch wall time), so a roster date matches its
     * own 00:00-23:59 slice with no timezone shifting. Shifting to UTC would
     * misattribute 21:00-23:59 punches to the next day and break evening
     * detection, presence proofs and checkout matching.
     *
     * @return array{0: string, 1: string}
     */
    private function localDayBounds(string $date): array
    {
        $day = Carbon::parse($date);

        return [
            $day->copy()->startOfDay()->format('Y-m-d H:i:s'),
            $day->copy()->endOfDay()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Normalize a raw punch timestamp to app-local time.
     *
     * Punch values arrive either as Eloquent Carbon instances (already in app
     * timezone) or as naive local wall-time strings — both are returned in
     * app timezone without any UTC shifting (see localDayBounds()).
     */
    private function toLocalTime(mixed $value): Carbon
    {
        $time = $value instanceof \DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse((string) $value);

        return $time->setTimezone(config('app.timezone'));
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
     * Whether a CLOSED previous-day session still hides a forgotten exit punch.
     *
     * Two healing paths fabricate a checkout without the employee recording
     * one on the duty day:
     *  1. the nightly auto-close job (notes carry "أغلق تلقائياً") which stamps
     *     the expected exit time — never a real punch;
     *  2. a later day's checkout punch (e.g. today's 15:02 exit) which the
     *     pipeline also writes onto the still-open previous session. Day-duty
     *     rotations (admin, 08:00-15:00) must checkout the same day, so a
     *     checkout dated after the duty day proves the exit was missed.
     *
     * Overnight duties are exempt from the second rule: their checkout
     * legitimately lands on the departure morning. Post-midnight checkouts
     * before 05:00 are exempt too — the night rule attaches them as genuine
     * late-night exits, not missed ones.
     */
    private function isHealedMissingCheckout(AttendanceSession $session, mixed $assignment): bool
    {
        if (is_string($session->notes) && str_contains($session->notes, 'أغلق تلقائياً')) {
            return true;
        }

        if ($session->attendance_date === null || $session->check_out_at === null) {
            return false;
        }

        if ($session->check_out_at->toDateString() <= $session->attendance_date->toDateString()) {
            return false;
        }

        if ((int) $session->check_out_at->format('H') < 5) {
            return false;
        }

        return ! $this->isAssignmentOvernight($assignment);
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

        // An exit deadline that falls BEFORE the session's own check-in cannot
        // belong to that shift: the rotation's window is being read on the
        // wrong calendar day (e.g. a next-morning 07:30-09:30 exit window of an
        // overnight rotation that has no time schedule is treated as same-day,
        // so it "ends" before an afternoon/evening arrival). Never claim a
        // missed exit the employee could not have missed yet.
        if ($session?->check_in_at && $windowEnd->lt($session->check_in_at)) {
            return false;
        }

        return now()->gte($windowEnd);
    }
}
