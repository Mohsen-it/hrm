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
 * Returns only the latest session per employee to avoid duplicates.
 */
class AttendanceViolationService
{
    public function __construct(
        private RotationAssignmentRepository $rotationAssignmentRepository,
        private RotationEngine $rotationEngine,
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
        $latestIds = $this->getLatestSessionIds($from, $to, $userId);

        $query = AttendanceSession::query()
            ->whereIn('id', $latestIds)
            ->whereNotNull('check_in_at')
            ->whereRaw('TIME(check_in_at) > ?', [$cutoffTime])
            ->with(['user.department']);

        $this->filterOnDuty($query, $this->getOnDutyByDate($from, $to));

        return $query
            ->orderBy('attendance_date')
            ->orderBy('check_in_at')
            ->get();
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

        return AttendanceSession::query()
            ->whereIn('id', $latestIds)
            ->whereNull('check_out_at')
            ->whereNotNull('check_in_at')
            ->whereRaw('TIME(check_in_at) <= ?', [$cutoffTime])
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
        $latestIds = $this->getLatestSessionIds($from, $to, $userId);

        return AttendanceSession::query()
            ->whereIn('id', $latestIds)
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

        foreach ($assignments as $assignment) {
            $rotation = $assignment->rotation;
            $group = $assignment->rotationGroup;

            if (! $rotation || ! $group) {
                continue;
            }

            $current = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->startOfDay();

            while ($current->lte($end)) {
                $date = $current->format('Y-m-d');

                if ($this->rotationEngine->isWorkDay($rotation, $group, $current)) {
                    $onDuty[$date][] = $assignment->employee_id;
                }

                $current->addDay();
            }
        }

        return array_map(
            fn (array $ids): array => array_values(array_unique($ids)),
            $onDuty
        );
    }
}
