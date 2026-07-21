<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonPeriod;
use Closure;
use Illuminate\Support\Facades\DB;
use Modules\Users\Repositories\UserRepository;

/**
 * DailyAttendanceAutoCalculationService — orchestrates the daily roll-ups.
 *
 * Drives `DailyAttendanceSummaryService` across one or many employees over one
 * or many calendar dates. The bulk entry points chunk the user set and the
 * date range so the operation stays predictable on memory and avoids N+1
 * queries during back-fills.
 */
class DailyAttendanceAutoCalculationService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private DailyAttendanceSummaryService $summaryService,
        private UserRepository $userRepository,
    ) {}

    // ------------------------------------------------------------------
    // Public entry points
    // ------------------------------------------------------------------

    /**
     * Rebuild the daily summary for one user on a single date.
     */
    public function calculateForUserAndDate(int $userId, string $date): int
    {
        $this->summaryService->recalculateForUserAndDate($userId, $date);

        return 1;
    }

    /**
     * Rebuild summaries for one user across an inclusive date range.
     *
     * @return int Number of (user, date) pairs rebuilt
     */
    public function calculateForUserAndRange(int $userId, string $from, string $to): int
    {
        $count = 0;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $this->summaryService->recalculateForUserAndDate($userId, $day->format('Y-m-d'));
            $count++;
        }

        return $count;
    }

    /**
     * Rebuild summaries for every active employee on a single date.
     *
     * The user set is queried in chunks via the UserRepository (no fetching of
     * all rows at once) and the super-admin is excluded automatically through
     * the repository's `withoutSuperAdmin` scope.
     *
     * @return int Number of (user, date) pairs rebuilt
     */
    public function calculateForDate(string $date): int
    {
        $count = 0;
        $chunk = (int) config('attendance.chunk_size', 200);

        $this->userRepository
            ->query()
            ->withoutSuperAdmin()
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('termination_date')
                    ->orWhere('termination_date', '>=', $date);
            })
            ->select(['id', 'name'])
            ->chunkById($chunk, function ($users) use ($date, &$count): void {
                DB::beginTransaction();
                try {
                    foreach ($users as $user) {
                        $this->summaryService->recalculateForUserAndDate($user->id, $date);
                        $count++;
                    }
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
            });

        return $count;
    }

    /**
     * Rebuild summaries for a date range across every active employee.
     *
     * Iterates the date range first, then "all-users-for-a-date" (`calculateForDate`)
     * for each day so the chunked user set stays bounded and the transaction
     * per day is consistent.
     *
     * Heavy callers should dispatch this onto a queue (the constitution flags
     * any operation that may exceed 2 seconds as queue-bound).
     *
     * @return int Number of (user, date) pairs rebuilt
     */
    public function calculateForDateRange(string $from, string $to): int
    {
        $count = 0;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $count += $this->calculateForDate($day->format('Y-m-d'));
        }

        return $count;
    }

    /**
     * Convenience: back-fill any missing summary rows for a date range.
     *
     * Only (user, date) pairs lacking a daily summary record are rebuilt.
     *
     * @param  callable|null  $onProgress  Optional callback (day, user, status) used by queue workers for progress reporting.
     */
    public function recalculateMissingForRange(string $from, string $to, ?Closure $onProgress = null): int
    {
        $count = 0;
        $chunk = (int) config('attendance.chunk_size', 200);

        $existingPairKeys = $this->existingPairKeysForRange($from, $to);

        $this->userRepository
            ->query()
            ->withoutSuperAdmin()
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->select(['id'])
            ->chunkById($chunk, function ($users) use ($from, $to, $existingPairKeys, $onProgress, &$count): void {
                foreach ($users as $user) {
                    foreach (CarbonPeriod::create($from, $to) as $day) {
                        $key = $user->id.'|'.$day->format('Y-m-d');
                        if (isset($existingPairKeys[$key])) {
                            continue;
                        }

                        $this->summaryService->recalculateForUserAndDate($user->id, $day->format('Y-m-d'));
                        $count++;

                        if ($onProgress) {
                            $onProgress($day->format('Y-m-d'), $user->id, 'rebuilt');
                        }
                    }
                }
            });

        return $count;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Build an in-memory map of "userId|date" keys that already have a summary
     * row inside the supplied range. The volume stays bounded because the
     * range endpoints are typically callers-controlled.
     *
     * @return array<string, true>
     */
    protected function existingPairKeysForRange(string $from, string $to): array
    {
        $rows = DB::table('daily_attendance_summaries')
            ->whereBetween('summary_date', [$from, $to])
            ->select(['user_id', 'summary_date'])
            ->get();

        $keys = [];
        foreach ($rows as $row) {
            $keys[$row->user_id.'|'.$row->summary_date] = true;
        }

        return $keys;
    }
}
