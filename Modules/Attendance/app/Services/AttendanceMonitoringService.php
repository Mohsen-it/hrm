<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\DailyAttendanceSummary;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Repositories\AttendanceSessionRepository;
use Modules\Attendance\Repositories\DailyAttendanceSummaryRepository;
use Modules\Attendance\Repositories\RawAttendanceLogRepository;
use Modules\Users\Repositories\UserRepository;

/**
 * AttendanceMonitoringService — real-time and near-real-time views.
 *
 * Powers the "Live" admin screen and the dashboard widgets by:
 *  - Listing employees currently inside an open session (no check-out yet).
 *  - Listing missing punches (sessions older than the configured threshold
 *    that have not been closed).
 *  - Detecting abnormal status counts (mass lateness / mass absence).
 *  - Detecting orphaned raw logs (no user attached, unprocessed).
 *
 * Everything is read-only; the service does not write to the DB.
 */
class AttendanceMonitoringService
{
    /**
     * Threshold (in minutes) after which an open session is considered
     * "missing a check-out" and surfaced on the monitoring dashboard.
     */
    public const DEFAULT_MISSING_CHECKOUT_MINUTES = 60;

    /**
     * Create a new service instance.
     */
    public function __construct(
        private AttendanceCacheService $cache,
        private AttendanceSessionRepository $sessionRepository,
        private DailyAttendanceSummaryRepository $summaryRepository,
        private RawAttendanceLogRepository $rawLogRepository,
        private UserRepository $userRepository,
    ) {}

    // ------------------------------------------------------------------
    // Real-time views
    // ------------------------------------------------------------------

    /**
     * Employees currently inside an open session (no check-out yet) today.
     *
     * @return Collection<int, AttendanceSession>
     */
    public function getLiveSessions(?string $date = null, int $limit = 200): Collection
    {
        $date = $date ?? CarbonImmutable::now()->toDateString();
        $cacheKey = $this->cache->key('live_sessions', [$date, $limit]);

        return $this->cache->remember($cacheKey, function () use ($date, $limit): Collection {
            return $this->sessionRepository->query()
                ->onDate($date)
                ->open()
                ->with(['user', 'shift'])
                ->orderBy('check_in_at')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Sessions whose check-out never landed within the expected window.
     *
     * @return Collection<int, AttendanceSession>
     */
    public function getMissingCheckouts(?string $date = null, int $thresholdMinutes = self::DEFAULT_MISSING_CHECKOUT_MINUTES): Collection
    {
        $date = $date ?? CarbonImmutable::now()->toDateString();
        $cacheKey = $this->cache->key('missing_checkouts', [$date, $thresholdMinutes]);

        return $this->cache->remember($cacheKey, function () use ($date, $thresholdMinutes): Collection {
            $threshold = CarbonImmutable::now()->subMinutes($thresholdMinutes);

            // An employee who recorded ANY exit punch that day has left —
            // stray open sessions must never turn a registered check-out into
            // a missing-checkout violation.
            $checkedOutUserIds = $this->sessionRepository->query()
                ->onDate($date)
                ->whereNotNull('check_out_at')
                ->distinct()
                ->pluck('user_id');

            return $this->sessionRepository->query()
                ->onDate($date)
                ->whereNotNull('check_in_at')
                ->whereNull('check_out_at')
                ->where('check_in_at', '<=', $threshold)
                ->whereNotIn('user_id', $checkedOutUserIds)
                ->with(['user', 'shift'])
                ->orderBy('check_in_at')
                ->get();
        });
    }

    /**
     * Daily summaries flagged with statuses that need operator attention.
     *
     * @return Collection<int, DailyAttendanceSummary>
     */
    public function getAnomalies(string $date, ?int $limit = 200): Collection
    {
        $cacheKey = $this->cache->key('anomalies', [$date, $limit]);

        return $this->cache->remember($cacheKey, function () use ($date, $limit): Collection {
            return $this->summaryRepository->query()
                ->onDate($date)
                ->whereIn('status', ['missing_punch', 'absent'])
                ->with(['user', 'shift'])
                ->orderBy('late_minutes', 'desc')
                ->when($limit, fn ($q, $l) => $q->limit($l))
                ->get();
        });
    }

    // ------------------------------------------------------------------
    // Health / counters
    // ------------------------------------------------------------------

    /**
     * Counts of various states across the live and processed streams.
     *
     * @return array{
     *     date: string,
     *     live_sessions: int,
     *     missing_checkouts: int,
     *     unprocessed_raw_logs: int,
     *     anomalies: int,
     *     generated_at: string
     * }
     */
    public function getHealthSnapshot(string $date, ?Collection $preFetchedLiveSessions = null): array
    {
        $liveSessions = $preFetchedLiveSessions ?? $this->getLiveSessions($date);

        return [
            'date' => $date,
            'live_sessions' => (int) $liveSessions->count(),
            'missing_checkouts' => (int) $this->getMissingCheckouts($date)->count(),
            'unprocessed_raw_logs' => $this->getUnprocessedRawLogCount($date),
            'anomalies' => (int) $this->getAnomalies($date)->count(),
            'generated_at' => CarbonImmutable::now()->toDateTimeString(),
        ];
    }

    /**
     * Detect a "mass lateness" event — when more than the supplied ratio of
     * scheduled employees are late on a given day.
     *
     * @return array{date: string, is_alert: bool, late_count: int, total: int, ratio: float, threshold: float}
     */
    public function detectMassLateness(string $date, float $ratio = 0.30): array
    {
        $cacheKey = $this->cache->key('mass_lateness', [$date, $ratio]);

        return $this->cache->remember($cacheKey, function () use ($date, $ratio): array {
            $total = (int) $this->summaryRepository->query()
                ->onDate($date)
                ->whereIn('status', ['present', 'late', 'early_leave', 'missing_punch', 'absent'])
                ->count();

            $late = (int) $this->summaryRepository->query()
                ->onDate($date)
                ->where('status', 'late')
                ->count();

            $computedRatio = $total > 0 ? round($late / $total, 4) : 0.0;

            return [
                'date' => $date,
                'is_alert' => $total > 0 && $computedRatio >= $ratio,
                'late_count' => $late,
                'total' => $total,
                'ratio' => $computedRatio,
                'threshold' => $ratio,
            ];
        });
    }

    /**
     * Detect a "mass absence" event — when more than the supplied ratio of
     * scheduled employees are absent on a given day.
     *
     * @return array{date: string, is_alert: bool, absent_count: int, total: int, ratio: float, threshold: float}
     */
    public function detectMassAbsence(string $date, float $ratio = 0.25): array
    {
        $cacheKey = $this->cache->key('mass_absence', [$date, $ratio]);

        return $this->cache->remember($cacheKey, function () use ($date, $ratio): array {
            $total = (int) $this->summaryRepository->query()
                ->onDate($date)
                ->whereIn('status', ['present', 'late', 'early_leave', 'missing_punch', 'absent'])
                ->count();

            $absent = (int) $this->summaryRepository->query()
                ->onDate($date)
                ->where('status', 'absent')
                ->count();

            $computedRatio = $total > 0 ? round($absent / $total, 4) : 0.0;

            return [
                'date' => $date,
                'is_alert' => $total > 0 && $computedRatio >= $ratio,
                'absent_count' => $absent,
                'total' => $total,
                'ratio' => $computedRatio,
                'threshold' => $ratio,
            ];
        });
    }

    // ------------------------------------------------------------------
    // Diagnostics
    // ------------------------------------------------------------------

    /**
     * Count raw logs that have not been correlated to a user yet
     * (orphaned or unmatched device punches).
     *
     * @return array{date: string, total: int, by_source: array<string, int>}
     */
    public function getUnprocessedRawSummary(string $date): array
    {
        $cacheKey = $this->cache->key('unprocessed_raw_summary', [$date]);

        return $this->cache->remember($cacheKey, function () use ($date): array {
            [$from, $to] = $this->dayRange($date);
            $bySource = $this->rawLogRepository->countUnprocessedBySourceBetween($from, $to);

            return [
                'date' => $date,
                'total' => array_sum($bySource),
                'by_source' => $bySource,
            ];
        });
    }

    /**
     * Count active employees who currently have no session for the day.
     */
    public function getEmployeesWithoutSession(string $date): int
    {
        $cacheKey = $this->cache->key('without_session', [$date]);

        return $this->cache->remember($cacheKey, function () use ($date): int {
            return $this->userRepository->query()
                ->withoutSuperAdmin()
                ->active()
                ->whereDoesntHave('attendanceSessions', fn ($query) => $query->onDate($date))
                ->count();
        });
    }

    /**
     * Return recent device punches for the live feed.
     *
     * @return Collection<int, RawAttendanceLog>
     */
    public function getRecentPunches(int $limit = 100, ?string $date = null): Collection
    {
        $dateRange = $date ? $this->dayRange($date) : null;

        return $this->rawLogRepository->getRecent(
            $limit,
            $dateRange[0] ?? null,
            $dateRange[1] ?? null,
        );
    }

    /**
     * Count unprocessed logs for a date using the composite processing index.
     */
    protected function getUnprocessedRawLogCount(string $date): int
    {
        $cacheKey = $this->cache->key('unprocessed_raw_count', [$date]);

        return $this->cache->remember($cacheKey, function () use ($date): int {
            [$from, $to] = $this->dayRange($date);

            return $this->rawLogRepository->countUnprocessedBetween($from, $to);
        });
    }

    /**
     * Build an inclusive, index-friendly timestamp range for a calendar day.
     *
     * @return array{0: string, 1: string}
     */
    protected function dayRange(string $date): array
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        return [$day->toDateTimeString(), $day->endOfDay()->toDateTimeString()];
    }
}
