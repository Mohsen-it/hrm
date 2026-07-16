<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\DailyAttendanceSummary;

/**
 * AttendanceReportService — ad-hoc reporting on top of the attendance tables.
 *
 * Provides the building blocks consumed by:
 *  - `MonthlyReportService` (calendar-month roll-ups),
 *  - `YearlyReportService` (calendar-year roll-ups),
 *  - the live dashboard and admin pages.
 *
 * All queries are read-only and stay in PHP land (no raw SQL with user input)
 * so the queries remain driver-agnostic (SQLite / MySQL / PostgreSQL).
 */
class AttendanceReportService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private AttendanceCacheService $cache,
    ) {}

    // ------------------------------------------------------------------
    // Per-user reports
    // ------------------------------------------------------------------

    /**
     * Build an attendance report for a single user inside a date range.
     *
     * @return array{
     *     user_id: int,
     *     from: string,
     *     to: string,
     *     totals: array<string, int|float>,
     *     by_status: array<string, int>,
     *     sessions: Collection<int, AttendanceSession>
     * }
     */
    public function getUserReport(int $userId, string $from, string $to): array
    {
        $cacheKey = $this->cache->key('user_report', [$userId, $from, $to]);

        return $this->cache->remember($cacheKey, function () use ($userId, $from, $to): array {
            $sessions = AttendanceSession::forUser($userId)
                ->betweenDates($from, $to)
                ->with(['shift'])
                ->orderBy('attendance_date')
                ->orderBy('check_in_at')
                ->get();

            $summaries = DailyAttendanceSummary::forUser($userId)
                ->betweenDates($from, $to)
                ->get();

            $byStatus = $this->countBy($summaries, 'status');
            $totals = [
                'work_minutes' => (int) $summaries->sum('total_work_minutes'),
                'break_minutes' => (int) $summaries->sum('total_break_minutes'),
                'overtime_minutes' => (int) $summaries->sum('total_overtime_minutes'),
                'late_minutes' => (int) $summaries->max('late_minutes'),
                'early_leave_minutes' => (int) $summaries->max('early_leave_minutes'),
                'sessions_count' => (int) $summaries->sum('sessions_count'),
                'days_present' => (int) $summaries->whereIn('status', ['present', 'late', 'early_leave'])->count(),
                'days_absent' => (int) $summaries->where('status', 'absent')->count(),
                'days_missing' => (int) $summaries->where('status', 'missing_punch')->count(),
            ];

            return [
                'user_id' => $userId,
                'from' => $from,
                'to' => $to,
                'totals' => $totals,
                'by_status' => $byStatus,
                'sessions' => $sessions,
            ];
        });
    }

    /**
     * Compute the overtime analysis for a single user inside a date range.
     *
     * @return array{user_id: int, from: string, to: string, overtime_minutes: int, overtime_sessions: int, by_day: array<string, int>}
     */
    public function getUserOvertimeReport(int $userId, string $from, string $to): array
    {
        $cacheKey = $this->cache->key('user_overtime', [$userId, $from, $to]);

        return $this->cache->remember($cacheKey, function () use ($userId, $from, $to): array {
            $rows = DailyAttendanceSummary::forUser($userId)
                ->betweenDates($from, $to)
                ->where('total_overtime_minutes', '>', 0)
                ->orderBy('summary_date')
                ->get(['summary_date', 'total_overtime_minutes']);

            $byDay = $rows->mapWithKeys(fn ($r) => [
                $r->summary_date->format('Y-m-d') => (int) $r->total_overtime_minutes,
            ])->all();

            return [
                'user_id' => $userId,
                'from' => $from,
                'to' => $to,
                'overtime_minutes' => (int) $rows->sum('total_overtime_minutes'),
                'overtime_sessions' => $rows->count(),
                'by_day' => $byDay,
            ];
        });
    }

    // ------------------------------------------------------------------
    // Department / branch reports
    // ------------------------------------------------------------------

    /**
     * Compare attendance KPIs across departments inside a date range.
     *
     * @return array<int, array{
     *     department_id: int,
     *     department_name: string|null,
     *     employees: int,
     *     present_days: int,
     *     absent_days: int,
     *     late_days: int,
     *     overtime_minutes: int,
     *     avg_work_minutes: float
     * }>
     */
    public function getDepartmentComparison(string $from, string $to): array
    {
        $cacheKey = $this->cache->key('dept_comparison', [$from, $to]);

        return $this->cache->remember($cacheKey, function () use ($from, $to): array {
            $rows = DB::table('daily_attendance_summaries as s')
                ->join('users as u', 'u.id', '=', 's.user_id')
                ->leftJoin('departments as d', 'd.id', '=', 'u.department_id')
                ->whereBetween('s.summary_date', [$from, $to])
                ->groupBy('u.department_id', 'd.department_name')
                ->selectRaw('
                    u.department_id as department_id,
                    d.department_name as department_name,
                    COUNT(DISTINCT u.id) as employees,
                    SUM(CASE WHEN s.status IN ("present", "late", "early_leave") THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN s.status = "absent" THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN s.status = "late" THEN 1 ELSE 0 END) as late_days,
                    COALESCE(SUM(s.total_overtime_minutes), 0) as overtime_minutes,
                    COALESCE(AVG(s.total_work_minutes), 0) as avg_work_minutes
                ')
                ->get();

            return $rows->map(fn ($row) => [
                'department_id' => $row->department_id !== null ? (int) $row->department_id : 0,
                'department_name' => $row->department_name,
                'employees' => (int) $row->employees,
                'present_days' => (int) $row->present_days,
                'absent_days' => (int) $row->absent_days,
                'late_days' => (int) $row->late_days,
                'overtime_minutes' => (int) $row->overtime_minutes,
                'avg_work_minutes' => round((float) $row->avg_work_minutes, 2),
            ])->all();
        });
    }

    // ------------------------------------------------------------------
    // Global / dashboard
    // ------------------------------------------------------------------

    /**
     * Build the headline attendance KPIs for a single day.
     *
     * @return array{
     *     date: string,
     *     present: int, late: int, absent: int, early_leave: int, missing_punch: int, total: int,
     *     by_status: array<string, int>
     * }
     */
    public function getDailyKpis(string $date): array
    {
        $cacheKey = $this->cache->key('daily_kpis', [$date]);

        return $this->cache->remember($cacheKey, function () use ($date): array {
            $byStatus = DailyAttendanceSummary::onDate($date)
                ->groupBy('status')
                ->selectRaw('status, COUNT(*) as c')
                ->pluck('c', 'status')
                ->all();

            $byStatus = array_map('intval', $byStatus);
            $present = ($byStatus['present'] ?? 0)
                + ($byStatus['late'] ?? 0)
                + ($byStatus['early_leave'] ?? 0);
            $late = $byStatus['late'] ?? 0;
            $absent = $byStatus['absent'] ?? 0;
            $earlyLeave = $byStatus['early_leave'] ?? 0;
            $missing = $byStatus['missing_punch'] ?? 0;
            $total = array_sum($byStatus);

            return [
                'date' => $date,
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'early_leave' => $earlyLeave,
                'missing_punch' => $missing,
                'total' => $total,
                'by_status' => $byStatus,
            ];
        });
    }

    /**
     * Build a daily-trend series for the supplied date range.
     *
     * @return array<int, array{date: string, present: int, absent: int, late: int, overtime_minutes: int}>
     */
    public function getDailyTrend(string $from, string $to): array
    {
        $cacheKey = $this->cache->key('daily_trend', [$from, $to]);

        return $this->cache->remember($cacheKey, function () use ($from, $to): array {
            $rows = DailyAttendanceSummary::betweenDates($from, $to)
                ->groupBy('summary_date')
                ->orderBy('summary_date')
                ->selectRaw('
                    summary_date,
                    SUM(CASE WHEN status IN ("present", "late", "early_leave") THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late,
                    COALESCE(SUM(total_overtime_minutes), 0) as overtime_minutes
                ')
                ->get();

            return $rows->map(fn ($row) => [
                'date' => $row->summary_date->format('Y-m-d'),
                'present' => (int) $row->present,
                'absent' => (int) $row->absent,
                'late' => (int) $row->late,
                'overtime_minutes' => (int) $row->overtime_minutes,
            ])->all();
        });
    }

    /**
     * Top late / absent employees inside a date range.
     *
     * @return array<int, array{user_id: int, name: string|null, late_minutes: int, absent_days: int}>
     */
    public function getTopLateEmployees(string $from, string $to, int $limit = 10): array
    {
        $cacheKey = $this->cache->key('top_late', [$from, $to, $limit]);

        return $this->cache->remember($cacheKey, function () use ($from, $to, $limit): array {
            $rows = DB::table('daily_attendance_summaries as s')
                ->join('users as u', 'u.id', '=', 's.user_id')
                ->whereBetween('s.summary_date', [$from, $to])
                ->groupBy('u.id', 'u.name')
                ->selectRaw('
                    u.id as user_id,
                    u.name as name,
                    COALESCE(MAX(s.late_minutes), 0) as late_minutes,
                    SUM(CASE WHEN s.status = "absent" THEN 1 ELSE 0 END) as absent_days
                ')
                ->orderByDesc('late_minutes')
                ->orderByDesc('absent_days')
                ->limit($limit)
                ->get();

            return $rows->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'name' => $row->name,
                'late_minutes' => (int) $row->late_minutes,
                'absent_days' => (int) $row->absent_days,
            ])->all();
        });
    }

    /**
     * Enumerate the calendar dates in the range `[from, to]`.
     *
     * @return array<int, string>
     */
    public function datesInRange(string $from, string $to): array
    {
        $out = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $out[] = $day->format('Y-m-d');
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Count rows grouped by a single column.
     *
     * @param  Collection<int, mixed>  $rows
     * @return array<string, int>
     */
    protected function countBy($rows, string $column): array
    {
        $out = [];
        foreach ($rows as $row) {
            $key = (string) $row->{$column};
            $out[$key] = ($out[$key] ?? 0) + 1;
        }

        return $out;
    }
}
