<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Users\Models\User;

/**
 * YearlyReportService — calendar-year attendance roll-ups.
 *
 * Sits on top of `AttendanceReportService` and `MonthlyReportService` and
 * composes them into year-wide KPIs, monthly sub-totals, and a per-user
 * yearly summary. All heavy results flow through `AttendanceCacheService`.
 */
class YearlyReportService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private AttendanceReportService $reportService,
        private MonthlyReportService $monthlyService,
        private AttendanceCacheService $cache,
    ) {}

    // ------------------------------------------------------------------
    // Top-level
    // ------------------------------------------------------------------

    /**
     * Headline KPIs for the supplied year.
     *
     * @return array{
     *     year: int, from: string, to: string,
     *     working_days: int,
     *     totals: array<string, int|float>,
     *     by_status: array<string, int>
     * }
     */
    public function getYearlyKpis(int $year): array
    {
        [$from, $to] = $this->resolveRange($year);
        $cacheKey = $this->cache->key('yearly_kpis', [$year]);

        return $this->cache->remember($cacheKey, function () use ($year, $from, $to): array {
            $rows = DB::table('daily_attendance_summaries')
                ->whereBetween('summary_date', [$from, $to])
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late_days,
                    SUM(CASE WHEN status = "early_leave" THEN 1 ELSE 0 END) as early_leave_days,
                    SUM(CASE WHEN status = "missing_punch" THEN 1 ELSE 0 END) as missing_punch_days,
                    SUM(CASE WHEN status = "holiday" THEN 1 ELSE 0 END) as holiday_days,
                    COALESCE(SUM(total_work_minutes), 0) as work_minutes,
                    COALESCE(SUM(total_overtime_minutes), 0) as overtime_minutes
                ')
                ->first();

            $byStatus = [
                'present' => (int) ($rows->present_days ?? 0),
                'absent' => (int) ($rows->absent_days ?? 0),
                'late' => (int) ($rows->late_days ?? 0),
                'early_leave' => (int) ($rows->early_leave_days ?? 0),
                'missing_punch' => (int) ($rows->missing_punch_days ?? 0),
                'holiday' => (int) ($rows->holiday_days ?? 0),
            ];

            return [
                'year' => $year,
                'from' => $from,
                'to' => $to,
                'working_days' => $this->workingDaysInYear($year),
                'totals' => [
                    'records' => (int) ($rows->total ?? 0),
                    'work_minutes' => (int) ($rows->work_minutes ?? 0),
                    'overtime_minutes' => (int) ($rows->overtime_minutes ?? 0),
                ],
                'by_status' => $byStatus,
            ];
        });
    }

    /**
     * 12-month breakdown for the supplied year (one row per month).
     *
     * @return array<int, array{month: int, from: string, to: string, records: int, work_minutes: int, overtime_minutes: int, present: int, absent: int, late: int}>
     */
    public function getYearlyMonthlyBreakdown(int $year): array
    {
        $cacheKey = $this->cache->key('yearly_monthly', [$year]);

        return $this->cache->remember($cacheKey, function () use ($year): array {
            $out = [];
            for ($m = 1; $m <= 12; $m++) {
                $kpi = $this->monthlyService->getMonthlyKpis($year, $m);
                $totals = $kpi['totals'];
                $by = $kpi['by_status'];

                $out[] = [
                    'month' => $m,
                    'from' => $kpi['from'],
                    'to' => $kpi['to'],
                    'records' => (int) ($totals['records'] ?? 0),
                    'work_minutes' => (int) ($totals['work_minutes'] ?? 0),
                    'overtime_minutes' => (int) ($totals['overtime_minutes'] ?? 0),
                    'present' => (int) $by['present'],
                    'absent' => (int) $by['absent'],
                    'late' => (int) $by['late'],
                ];
            }

            return $out;
        });
    }

    /**
     * Per-user yearly summary.
     *
     * @return array<int, array{
     *     user_id: int, name: string|null,
     *     present_days: int, absent_days: int, late_days: int, missing_days: int,
     *     work_minutes: int, overtime_minutes: int
     * }>
     */
    public function getUserYearlyReport(int $year): array
    {
        [$from, $to] = $this->resolveRange($year);
        $cacheKey = $this->cache->key('user_yearly', [$year]);

        return $this->cache->remember($cacheKey, function () use ($from, $to): array {
            $rows = DB::table('daily_attendance_summaries as s')
                ->join('users as u', 'u.id', '=', 's.user_id')
                ->where('u.id', '!=', User::SUPER_ADMIN_ID)
                ->whereBetween('s.summary_date', [$from, $to])
                ->groupBy('u.id', 'u.name')
                ->selectRaw('
                    u.id as user_id,
                    u.name as name,
                    SUM(CASE WHEN s.status = "present" THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN s.status = "absent" THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN s.status = "late" THEN 1 ELSE 0 END) as late_days,
                    SUM(CASE WHEN s.status = "missing_punch" THEN 1 ELSE 0 END) as missing_days,
                    COALESCE(SUM(s.total_work_minutes), 0) as work_minutes,
                    COALESCE(SUM(s.total_overtime_minutes), 0) as overtime_minutes
                ')
                ->orderBy('u.name')
                ->get();

            return $rows->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'name' => $row->name,
                'present_days' => (int) $row->present_days,
                'absent_days' => (int) $row->absent_days,
                'late_days' => (int) $row->late_days,
                'missing_days' => (int) $row->missing_days,
                'work_minutes' => (int) $row->work_minutes,
                'overtime_minutes' => (int) $row->overtime_minutes,
            ])->all();
        });
    }

    /**
     * Per-department yearly KPIs.
     *
     * @return array<int, array{
     *     department_id: int, department_name: string|null,
     *     employees: int, present_days: int, absent_days: int, late_days: int, overtime_minutes: int
     * }>
     */
    public function getDepartmentYearlyReport(int $year): array
    {
        [$from, $to] = $this->resolveRange($year);
        $cacheKey = $this->cache->key('dept_yearly', [$year]);

        return $this->cache->remember($cacheKey, function () use ($from, $to): array {
            $rows = DB::table('daily_attendance_summaries as s')
                ->join('users as u', 'u.id', '=', 's.user_id')
                ->leftJoin('departments as d', 'd.id', '=', 'u.department_id')
                ->where('u.id', '!=', User::SUPER_ADMIN_ID)
                ->whereBetween('s.summary_date', [$from, $to])
                ->groupBy('u.department_id', 'd.department_name')
                ->selectRaw('
                    u.department_id as department_id,
                    d.department_name as department_name,
                    COUNT(DISTINCT u.id) as employees,
                    SUM(CASE WHEN s.status IN ("present", "late", "early_leave") THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN s.status = "absent" THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN s.status = "late" THEN 1 ELSE 0 END) as late_days,
                    COALESCE(SUM(s.total_overtime_minutes), 0) as overtime_minutes
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
            ])->all();
        });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Compute the `[from, to]` ISO range for the supplied year.
     *
     * @return array{0: string, 1: string}
     */
    protected function resolveRange(int $year): array
    {
        $start = CarbonImmutable::create($year, 1, 1)->startOfYear();
        $end = $start->endOfYear();

        return [$start->toDateString(), $end->toDateString()];
    }

    /**
     * Number of working days (Mon–Fri) in the supplied year.
     */
    protected function workingDaysInYear(int $year): int
    {
        $count = 0;
        $start = CarbonImmutable::create($year, 1, 1)->startOfYear();
        $end = $start->endOfYear();

        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
            if (! in_array((int) $d->format('N'), [5, 6], true)) {
                $count++;
            }
        }

        return $count;
    }
}
