<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Attendance\Models\AttendanceSession;
use Modules\FingerprintDevices\Models\UserFingerprint;
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
        ?int $departmentId = null,
        ?int $userId = null,
        ?string $statusFilter = null,
    ): array {
        $day = Carbon::parse($date)->startOfDay();
        $date = $day->toDateString();
        $monthFrom = $day->copy()->startOfMonth()->toDateString();
        $monthTo = $day->copy()->endOfMonth()->toDateString();

        $users = User::query()->employees()->active()
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>=', $date))
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
        $monthSessions = AttendanceSession::betweenDates($monthFrom, $monthTo)
            ->whereIn('user_id', $userIds)->whereNotNull('check_in_at')
            ->orderBy('check_in_at')->get()->groupBy('user_id');
        $fingerprints = UserFingerprint::whereIn('user_id', $userIds)->pluck('user_id')->flip();
        $vacations = UserVacationRequest::approved()->whereIn('user_id', $userIds)
            ->overlapping($date, $date)->with('vacationType')->get()->keyBy('user_id');
        $exceptions = ShiftException::active()->whereIn('employee_id', $userIds)
            ->whereIn('exception_type', ['leave', 'mission', 'training', 'swap'])
            ->overlapping($date)->get()->groupBy('employee_id');

        $rows = $users->map(function (User $user) use ($date, $cutoffTime, $expected, $assignments, $sessions, $monthSessions, $fingerprints, $vacations, $exceptions): array {
            $userSessions = $sessions->get($user->id, collect());
            $first = $userSessions->first();
            $last = $userSessions->last();
            $vacation = $vacations->get($user->id);
            $exception = $exceptions->get($user->id, collect())->first();
            $hasIn = $userSessions->contains(fn ($s) => $s->check_in_at !== null);
            $hasOut = $userSessions->contains(fn ($s) => $s->check_out_at !== null);
            $expectedCheckOut = $this->expectedCheckOut($assignments->get($user->id));
            $onMission = $exception?->exception_type === 'mission' || $this->isMission($vacation);
            $onLeave = $vacation !== null || in_array($exception?->exception_type, ['leave', 'training', 'swap'], true);
            $late = $first?->check_in_at && $first->check_in_at->format('H:i') > $cutoffTime;
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
            } elseif (! $fingerprints->has($user->id)) {
                $status = 'no_fingerprint';
                $label = 'لا توجد بصمة مسجلة';
            } elseif (($hasIn xor $hasOut) && $this->isIncompletePunchDue($date, $expectedCheckOut, $user->id, $expected)) {
                $status = 'incomplete';
                $label = $hasIn ? 'دخول دون خروج' : 'خروج دون دخول';
            } elseif ($late) {
                $status = 'late';
                $label = 'متأخر';
            }

            $notes = [];
            if ($lateCount > 0) {
                $notes[] = "تأخر {$lateCount} مرة هذا الشهر";
            }
            if ($status === 'incomplete') {
                $notes[] = 'بعد انتهاء الدوام المتوقع '.($expectedCheckOut ?? '—');
            }
            if ($status === 'no_fingerprint') {
                $notes[] = 'الموظف غير مسجل في جهاز البصمة';
            }

            return [
                'id' => $user->id, 'name' => $user->full_name, 'employee_code' => $user->employee_code,
                'department_name' => $user->department?->department_name ?? '—', 'status' => $status, 'status_label' => $label,
                'check_in' => $first?->check_in_at?->format('H:i') ?? '', 'check_out' => $last?->check_out_at?->format('H:i') ?? '',
                'expected' => $expected->has($user->id), 'expected_check_out' => $expectedCheckOut,
                'late_minutes' => $late && $first?->check_in_at ? $first->check_in_at->diffInMinutes(Carbon::parse($date.' '.$cutoffTime)) : 0,
                'notes' => implode('، ', $notes),
            ];
        })->when($statusFilter, fn ($collection) => $collection->where('status', $statusFilter))->values();

        return ['date' => $date, 'cutoff_time' => $cutoffTime, 'rows' => $rows, 'stats' => $rows->countBy('status')->all() + ['total' => $rows->count()]];
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

    /** Resolve the scheduled checkout time from the employee's active rotation. */
    private function expectedCheckOut(mixed $assignment): ?string
    {
        if (! $assignment) {
            return null;
        }

        return $this->rotationEngine->resolveTimes($assignment)['check_out'] ?? null;
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
