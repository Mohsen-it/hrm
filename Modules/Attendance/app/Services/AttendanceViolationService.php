<?php

namespace Modules\Attendance\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Attendance\Models\AttendanceSession;

/**
 * AttendanceViolationService — fetches employees violating time rules.
 *
 * Queries attendance_sessions directly for always-up-to-date results.
 * Returns only the latest session per employee to avoid duplicates.
 */
class AttendanceViolationService
{
    /**
     * Employees who checked in after the specified cutoff time.
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

        return AttendanceSession::query()
            ->whereIn('id', $latestIds)
            ->whereNotNull('check_in_at')
            ->whereRaw('TIME(check_in_at) > ?', [$cutoffTime])
            ->with(['user'])
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
            ->with(['user'])
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
            ->with(['user'])
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
}
