<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\DailyAttendanceSummary;
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

        $sessions = AttendanceSession::onDate($date)->whereIn('user_id', $userIds)
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

        $rows = $users->map(function (User $user) use ($date, $cutoffTime, $expected, $assignments, $sessions, $monthSessions, $monthlyAbsenceCounts, $monthlyVacationDays, $unregisteredFingerprintIds, $vacations, $exceptions): array {
            $userSessions = $sessions->get($user->id, collect());
            $first = $userSessions->first();
            $last = $userSessions->last();
            $vacation = $vacations->get($user->id);
            $exception = $exceptions->get($user->id, collect())->first();
            $openSession = $userSessions
                ->filter(fn ($session) => $session->check_in_at !== null && $session->check_out_at === null)
                ->sortByDesc('check_in_at')
                ->first();
            $expectedCheckOut = $this->sessionExpectedCheckOut($openSession)
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
            $hasIncompletePunch = ! $onMission && ! $onLeave && $openSession !== null
                && $this->isIncompletePunchDue($date, $expectedCheckOut, $user->id, $expected);
            $lateCount = $monthSessions->get($user->id, collect())->filter(
                fn ($s) => $s->check_in_at && $s->check_in_at->format('H:i') > $cutoffTime
            )->unique(fn ($s) => $s->attendance_date?->toDateString())->count();

            $status = 'present';
            $label = 'حاضر';
            if ($onMission) {
                $status = 'mission';
                $label = 'مهمة سفر';
            } elseif ($onLeave) {
                $status = 'leave';
                $label = 'إجازة';
            } elseif (! $expected->has($user->id)) {
                $status = 'rest';
                $label = 'غير متوقع دوامه';
            } elseif (! $userSessions->count()) {
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
            if ($hasIncompletePunch) {
                $notes[] = 'بعد انتهاء الدوام المتوقع '.($expectedCheckOut ?? '—');
            }
            if ($hasNoFingerprint) {
                $notes[] = 'الموظف غير مسجل في جهاز البصمة';
            }

            return [
                'id' => $user->id, 'name' => $user->full_name, 'employee_code' => $user->employee_code,
                'department_name' => $user->department?->department_name ?? '—', 'status' => $status, 'status_label' => $label,
                'check_in' => $first?->check_in_at?->format('H:i') ?? '', 'check_out' => $last?->check_out_at?->format('H:i') ?? '',
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
     * A missing direction is a violation only once the employee's shift has ended.
     * This prevents the report from flagging everyone who is still at work.
     */
    private function isIncompletePunchDue(string $date, ?string $expectedCheckOut, int $userId, Collection $expected): bool
    {
        if (! $expected->has($userId) || ! $expectedCheckOut) {
            return false;
        }

        $reportDay = Carbon::parse($date)->startOfDay();
        if ($reportDay->lt(now()->startOfDay())) {
            return true;
        }
        if ($reportDay->gt(now()->startOfDay())) {
            return false;
        }

        return now()->gte(Carbon::parse($date.' '.$expectedCheckOut)->addMinutes(30));
    }
}
