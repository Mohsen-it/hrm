<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Services\RotationEngine;

/**
 * AttendanceViolationService — fetches employees violating time rules.
 *
 * Queries attendance_sessions directly for always-up-to-date results.
 */
class AttendanceViolationService
{
    public function __construct(
        private RotationAssignmentRepository $rotationAssignmentRepository,
        private RotationEngine $rotationEngine,
        private PunchWindowService $punchWindowService,
    ) {}

    /**
     * Employees who checked in after the specified cutoff time.
     *
     * Only employees whose rotation assignment marks the day as a work day
     * (on-duty per الدورية) are included — off-duty employees are excluded.
     *
     * @return Collection<int, AttendanceSession>
     */
    public function getLateCheckIns(
        string $from,
        string $to,
        string $cutoffTime,
        ?int $userId = null,
    ): Collection {
        $firstCheckInIds = $this->getFirstCheckInSessionIds($from, $to, $userId);

        $query = AttendanceSession::query()
            ->whereIn('id', $firstCheckInIds)
            ->whereNotNull('check_in_at')
            ->whereRaw('TIME(check_in_at) > ?', [$cutoffTime])
            ->with(['user.department']);

        $this->filterOnDuty($query, $this->getOnDutyByDate($from, $to));

        return $query
            ->orderBy('attendance_date')
            ->orderBy('check_in_at')
            ->get()
            ->filter(function (AttendanceSession $session) use ($cutoffTime): bool {
                $date = $session->attendance_date?->toDateString();

                return $date !== null
                    && $this->punchWindowService->hasCheckInWindowStarted($session->user_id, $date, $cutoffTime)
                    && $this->punchWindowService->isCheckInPunch($session->user_id, $session->check_in_at);
            })
            ->values();
    }

    /**
     * Employees who checked in before the cutoff but have not punched out.
     *
     * @return Collection<int, AttendanceSession>
     */
    public function getMissingCheckOuts(
        string $from,
        string $to,
        string $cutoffTime,
        ?int $userId = null,
    ): Collection {
        $latestIds = $this->getLatestSessionIds($from, $to, $userId);

        // An employee who recorded ANY exit punch in the period has left —
        // stray open sessions (midnight punches, repeated visits) must never
        // turn a registered check-out into a missing-checkout violation.
        $checkedOutUserIds = AttendanceSession::query()
            ->betweenDates($from, $to)
            ->whereNotNull('check_out_at')
            ->when($userId, fn ($q, $id) => $q->forUser($id))
            ->distinct()
            ->pluck('user_id');

        return AttendanceSession::query()
            ->whereIn('id', $latestIds)
            ->whereNull('check_out_at')
            ->whereNotNull('check_in_at')
            ->whereRaw('TIME(check_in_at) <= ?', [$cutoffTime])
            ->whereNotIn('user_id', $checkedOutUserIds)
            ->with(['user.department'])
            ->orderBy('attendance_date')
            ->orderBy('check_in_at')
            ->get();
    }

    /**
     * Employees who checked in after the specified cutoff and need to file
     * a vacation/leave request.
     *
     * @return Collection<int, AttendanceSession>
     */
    public function getLateForVacation(
        string $from,
        string $to,
        string $cutoffTime,
        ?int $userId = null,
    ): Collection {
        $firstCheckInIds = $this->getFirstCheckInSessionIds($from, $to, $userId);

        return AttendanceSession::query()
            ->whereIn('id', $firstCheckInIds)
            ->whereNotNull('check_in_at')
            ->whereRaw('TIME(check_in_at) > ?', [$cutoffTime])
            ->with(['user.department'])
            ->orderBy('attendance_date')
            ->orderBy('check_in_at')
            ->get();
    }

    /**
     * Get the latest session ID per user within a date range.
     *
     * @return array<int>
     */
    private function getLatestSessionIds(string $from, string $to, ?int $userId = null): array
    {
        $query = AttendanceSession::query()
            ->selectRaw('MAX(id) as latest_id')
            ->betweenDates($from, $to)
            ->when($userId, fn ($q, $id) => $q->forUser($id))
            ->groupBy('user_id');

        return $query->pluck('latest_id')->toArray();
    }

    /**
     * Get the first real check-in session ID for every employee and date.
     *
     * A second biometric punch must never make an employee late when they
     * already checked in before the configured cutoff. Ordering by timestamp
     * then ID makes equal-time punches deterministic as well.
     *
     * @return array<int, int>
     */
    private function getFirstCheckInSessionIds(string $from, string $to, ?int $userId = null): array
    {
        return AttendanceSession::query()
            ->betweenDates($from, $to)
            ->whereNotNull('check_in_at')
            ->when($userId, fn ($query, $id) => $query->forUser($id))
            ->orderBy('attendance_date')
            ->orderBy('user_id')
            ->orderBy('check_in_at')
            ->orderBy('id')
            ->get(['id', 'user_id', 'attendance_date'])
            ->unique(fn (AttendanceSession $session) => $session->user_id.'|'.$session->attendance_date?->toDateString())
            ->pluck('id')
            ->all();
    }

    /**
     * Restrict a session query to employees who are on-duty (work day) per
     * their rotation assignment on each matching attendance date.
     *
     * @param  array<string, array<int, int>>  $onDutyByDate  date => user IDs
     */
    private function filterOnDuty(Builder $query, array $onDutyByDate): void
    {
        if ($onDutyByDate === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $q) use ($onDutyByDate): void {
            foreach ($onDutyByDate as $date => $userIds) {
                $q->orWhere(function (Builder $sub) use ($date, $userIds): void {
                    $sub->where('attendance_date', $date)->whereIn('user_id', $userIds);
                });
            }
        });
    }

    /**
     * Map each date in the inclusive range to the user IDs whose rotation
     * assignment marks that date as a work day (i.e. on-duty).
     *
     * @return array<string, array<int, int>>
     */
    private function getOnDutyByDate(string $from, string $to): array
    {
        $assignments = $this->rotationAssignmentRepository->getAssignmentsOverlapping($from, $to);
        $onDuty = [];

        $current = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        while ($current->lte($end)) {
            $date = $current->toDateString();

            // Only an assignment effective on this exact date may decide
            // duty status. The repository puts latest transfers first, so a
            // legacy overlap keeps the employee's newest group/duty rotation.
            $effectiveAssignments = $assignments
                ->filter(fn ($assignment) => $assignment->start_date->toDateString() <= $date
                    && ($assignment->end_date === null || $assignment->end_date->toDateString() >= $date))
                ->unique('employee_id');

            foreach ($effectiveAssignments as $assignment) {
                $rotation = $assignment->rotation;
                $group = $assignment->rotationGroup;

                if ($rotation && $group && $this->rotationEngine->isWorkDay($rotation, $group, $current)) {
                    $onDuty[$date][] = $assignment->employee_id;
                }
            }

            $current->addDay();
        }

        return array_map(
            fn (array $ids): array => array_values(array_unique($ids)),
            $onDuty
        );
    }
}
