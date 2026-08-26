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
     * Uses accurate overtime calculation based on scheduled shift hours:
     * overtime = actual_work_minutes - expected_work_minutes (if positive)
     *
     * @return array{user_id: int, from: string, to: string, overtime_minutes: int, overtime_sessions: int, by_day: array<string, int>, daily_details: array<int, array<string, mixed>>}
     */
    public function getUserOvertimeReport(int $userId, string $from, string $to): array
    {
        $cacheKey = $this->cache->key('user_overtime_v2', [$userId, $from, $to]);

        return $this->cache->remember($cacheKey, function () use ($userId, $from, $to): array {
            $rows = DailyAttendanceSummary::forUser($userId)
                ->betweenDates($from, $to)
                ->orderBy('summary_date')
                ->get();

            $totalOvertime = 0;
            $overtimeDays = 0;
            $dailyDetails = [];

            foreach ($rows as $row) {
                $expectedWorkMinutes = $this->calculateExpectedWorkMinutes($row);
                $actualWorkMinutes = (int) $row->total_work_minutes;
                $overtimeMinutes = max(0, $actualWorkMinutes - $expectedWorkMinutes);

                $date = $row->summary_date->format('Y-m-d');
                $dailyDetails[] = [
                    'date' => $date,
                    'day_name' => $row->summary_date->locale(config('app.locale'))->translatedFormat('l'),
                    'expected_check_in' => $row->expected_check_in,
                    'expected_check_out' => $row->expected_check_out,
                    'actual_check_in' => $row->first_check_in_at?->format('H:i'),
                    'actual_check_out' => $row->last_check_out_at?->format('H:i'),
                    'work_minutes' => $actualWorkMinutes,
                    'expected_work_minutes' => $expectedWorkMinutes,
                    'overtime_minutes' => $overtimeMinutes,
                    'status' => $row->status,
                ];

                if ($overtimeMinutes > 0) {
                    $totalOvertime += $overtimeMinutes;
                    $overtimeDays++;
                }
            }

            return [
                'user_id' => $userId,
                'from' => $from,
                'to' => $to,
                'overtime_minutes' => $totalOvertime,
                'overtime_sessions' => $overtimeDays,
                'by_day' => collect($dailyDetails)
                    ->filter(fn ($d) => $d['overtime_minutes'] > 0)
                    ->mapWithKeys(fn ($d) => [$d['date'] => $d['overtime_minutes']])
                    ->all(),
                'daily_details' => $dailyDetails,
            ];
        });
    }

    /**
     * Calculate expected work minutes based on shift schedule.
     */
    private function calculateExpectedWorkMinutes(DailyAttendanceSummary $row): int
    {
        if (! $row->expected_check_in || ! $row->expected_check_out) {
            return 0;
        }

        $checkIn = $this->parseTimeToMinutes($row->expected_check_in);
        $checkOut = $this->parseTimeToMinutes($row->expected_check_out);

        if ($checkIn === null || $checkOut === null) {
            return 0;
        }

        $expectedMinutes = $checkOut - $checkIn;

        // Handle overnight shifts (e.g., check-in 22:00, check-out 06:00)
        if ($expectedMinutes < 0) {
            $expectedMinutes += 24 * 60;
        }

        // Subtract scheduled break time if available
        $breakMinutes = (int) $row->total_break_minutes;
        if ($breakMinutes > 0) {
            $expectedMinutes = max(0, $expectedMinutes - $breakMinutes);
        }

        return $expectedMinutes;
    }

    /**
     * Parse a time string (HH:MM or HH:MM:SS) to minutes since midnight.
     */
    private function parseTimeToMinutes(?string $time): ?int
    {
        if (! $time) {
            return null;
        }

        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return null;
        }

        $hours = (int) $parts[0];
        $minutes = (int) $parts[1];

        return $hours * 60 + $minutes;
    }

    /**
     * Compute overtime from monthly log data (same source as monthly log table).
     *
     * Improved accuracy:
     * - Work days: overtime = actual checkout - expected checkout (ignoring late arrival).
     *   Handles overnight shifts, applies a minimal grace (5 min) to avoid noise.
     * - Rest / non-work days: any work counts as overtime (span from first to last punch).
     * - Break time is subtracted from expected duration for the "expected work" display,
     *   while overtime via checkout diff is break-independent.
     * - Falls back to span-based (actual - expected) when checkout times are missing.
     *
     * @param  array<int, array<string, mixed>>  $monthlyLog  Monthly log data from MonthlyEmployeeAttendanceLogService
     * @return array{user_id: int, from: string, to: string, overtime_minutes: int, overtime_sessions: int, by_day: array<string, int>, daily_details: array<int, array<string, mixed>>}
     */
    public function getUserOvertimeReportFromMonthlyLog(int $userId, string $from, string $to, array $monthlyLog): array
    {
        // Cross-reference with DailyAttendanceSummary — secondary source for display fallback.
        $summaries = DailyAttendanceSummary::forUser($userId)
            ->betweenDates($from, $to)
            ->get()
            ->keyBy(fn ($s) => $s->summary_date->format('Y-m-d'));

        // Minimal grace to filter biometric noise — deliberately smaller than the 60m
        // session-level grace so the report is accurate to the minute. 5 min avoids
        // counting 1-2 min clock drift as overtime while still reporting real overtime.
        $reportGrace = 5;

        $totalOvertime = 0;
        $overtimeDays = 0;
        $dailyDetails = [];

        foreach ($monthlyLog as $day) {
            $date = $day['date'];
            $summary = $summaries->get($date);
            $isWorkDay = (bool) ($day['is_work_day'] ?? false);
            $summaryWorkMinutes = (int) ($summary->total_work_minutes ?? 0);
            $breakMinutes = (int) ($summary->total_break_minutes ?? 0);

            $expectedCheckIn = $day['expected_check_in'] ?? $summary?->expected_check_in;
            $expectedCheckOut = $day['expected_check_out'] ?? $summary?->expected_check_out;
            $expectedWorkMinutes = $this->calculateExpectedWorkMinutesFromTimes($expectedCheckIn, $expectedCheckOut);
            // Subtract scheduled break for the displayed expected duration.
            if ($breakMinutes > 0 && $expectedWorkMinutes > 0) {
                $expectedWorkMinutes = max(0, $expectedWorkMinutes - $breakMinutes);
            }

            // Monthly log is the strict source of truth for punches: only punches
            // inside configured windows. Summary's first/last is polluted by
            // duplicate/phantom sessions (always 15:23) — never fallback to it.
            $actualCheckInRaw = $day['first_check_in_at'];
            $actualCheckOutRaw = $day['last_check_out_at'];
            $actualCheckInDisplay = $this->formatTimeForDisplay($actualCheckInRaw);
            $actualCheckOutDisplay = $this->formatTimeForDisplay($actualCheckOutRaw);

            // Actual work is the span between the window-filtered punches from
            // the monthly log (accurate). Summary total_work is polluted by
            // overlapping duplicate sessions (e.g. 6460m vs 370m real) — ignore it
            // when a valid span exists.
            $actualSpanMinutes = $this->calculateActualWorkMinutes($actualCheckInRaw, $actualCheckOutRaw);
            $workMinutes = $actualSpanMinutes;
            if ($workMinutes === 0 && $summaryWorkMinutes > 0) {
                // No valid window punches but summary has data (rare): use summary as fallback.
                $workMinutes = $summaryWorkMinutes;
            }

            $overtimeMinutes = 0;

            if (! $isWorkDay) {
                // Rest / leave / unassigned: any work is overtime.
                if ($actualCheckInRaw && $actualCheckOutRaw) {
                    $overtimeMinutes = $this->calculateActualWorkMinutes($actualCheckInRaw, $actualCheckOutRaw);
                    // Subtract break if it falls on a rest-day work.
                    if ($breakMinutes > 0) {
                        $overtimeMinutes = max(0, $overtimeMinutes - $breakMinutes);
                    }
                } elseif ($summaryWorkMinutes > 0) {
                    $overtimeMinutes = $summaryWorkMinutes;
                }
                // No grace on rest days — every minute counts.
            } else {
                // Work day: overtime needs a valid checkout (window-filtered). Check-in
                // may be missing (نسيت بصمة دخول) — still count overtime via checkout diff
                // so those days reappear in the report as requested.
                if (! $actualCheckOutRaw) {
                    $overtimeMinutes = 0;
                } else {
                    $checkoutOvertime = $this->calculateCheckoutOvertime($date, $expectedCheckIn, $expectedCheckOut, $actualCheckOutRaw);
                    if ($checkoutOvertime !== null) {
                        $overtimeMinutes = $checkoutOvertime > $reportGrace ? $checkoutOvertime : 0;
                    } else {
                        // Fallback: span-based only when checkout parsing truly fails.
                        $overtimeMinutes = max(0, $workMinutes - $expectedWorkMinutes);
                        if ($overtimeMinutes <= $reportGrace) {
                            $overtimeMinutes = 0;
                        }
                    }
                }
            }

            // Do not fallback to summary's total_overtime_minutes: it is polluted
            // by duplicate sessions and aggregates (e.g. always 0 or 6460m) and
            // would diverge from the monthly log table the user compares against.

            $dailyDetails[] = [
                'date' => $date,
                'day_name' => $day['day_name'] ?? '',
                'expected_check_in' => $expectedCheckIn ? substr($expectedCheckIn, 0, 5) : null,
                'expected_check_out' => $expectedCheckOut ? substr($expectedCheckOut, 0, 5) : null,
                'actual_check_in' => $actualCheckInDisplay,
                'actual_check_out' => $actualCheckOutDisplay,
                'expected_work_minutes' => $expectedWorkMinutes,
                'work_minutes' => $workMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'overtime_human' => $this->formatMinutesHuman($overtimeMinutes),
                'status' => $day['schedule_status'] ?? ($isWorkDay ? 'work' : 'rest'),
            ];

            if ($overtimeMinutes > 0) {
                $totalOvertime += $overtimeMinutes;
                $overtimeDays++;
            }
        }

        return [
            'user_id' => $userId,
            'from' => $from,
            'to' => $to,
            'overtime_minutes' => $totalOvertime,
            'overtime_human' => $this->formatMinutesHuman($totalOvertime),
            'overtime_sessions' => $overtimeDays,
            'by_day' => collect($dailyDetails)
                ->filter(fn ($d) => $d['overtime_minutes'] > 0)
                ->mapWithKeys(fn ($d) => [$d['date'] => $d['overtime_minutes']])
                ->all(),
            'daily_details' => $dailyDetails,
        ];
    }

    /**
     * Overtime as minutes past expected checkout.
     *
     * Handles overnight shifts where checkout is next calendar day.
     *
     * @return int|null null when parsing fails
     */
    private function calculateCheckoutOvertime(string $date, ?string $expectedIn, ?string $expectedOut, ?string $actualOut): ?int
    {
        if (! $expectedOut || ! $actualOut) {
            return null;
        }

        $expectedDt = $this->buildExpectedDateTime($date, $expectedIn, $expectedOut);
        $actualDt = $this->parseDateTime($actualOut);

        if (! $expectedDt || ! $actualDt) {
            return null;
        }

        $diff = (int) round(($actualDt->getTimestamp() - $expectedDt->getTimestamp()) / 60);

        if ($diff < 0) {
            return 0;
        }

        // Guard against phantom next-day punches wrongly linked to this date
        // (e.g. summary last_check_out = next day 15:00). For non-overnight
        // schedules a diff > 12h is almost certainly a data error — cap to 0
        // so it does not inflate overtime. Overnight duties legitimately have
        // checkout next morning, but their diff is < 12h (e.g. 22:00-06:00 = 8h).
        if ($diff > 720) {
            $isOvernight = false;
            if ($expectedIn) {
                $inMin = $this->parseTimeToMinutes($expectedIn);
                $outMin = $this->parseTimeToMinutes($expectedOut);
                if ($inMin !== null && $outMin !== null && $outMin < $inMin) {
                    $isOvernight = true;
                }
            }
            if (! $isOvernight) {
                return 0;
            }
            // For overnight, cap diff at 12h as well — anything larger is data error.
            if ($diff > 720) {
                return 0;
            }
        }

        return $diff;
    }

    /**
     * Build expected checkout datetime, handling overnight (out < in => next day).
     */
    private function buildExpectedDateTime(string $date, ?string $expectedIn, string $expectedOut): ?\DateTimeImmutable
    {
        $out = $this->parseTimeToMinutes($expectedOut);
        if ($out === null) {
            return null;
        }
        $outStr = substr($expectedOut, 0, 5);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date.' '.$outStr);
        if (! $dt) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date.' '.$outStr.':00');
        }
        if (! $dt) {
            return null;
        }
        // Overnight detection: checkout earlier than check-in.
        if ($expectedIn) {
            $in = $this->parseTimeToMinutes($expectedIn);
            if ($in !== null && $out < $in) {
                $dt = $dt->modify('+1 day');
            }
        }

        return $dt;
    }

    /**
     * Parse a datetime string (Y-m-d H:i[:s] or H:i) to DateTimeImmutable.
     */
    private function parseDateTime(?string $value): ?\DateTimeImmutable
    {
        if (! $value) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i', $value)
            ?: null;
        if ($dt) {
            return $dt;
        }
        // Fallback via strtotime parsing.
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return (new \DateTimeImmutable)->setTimestamp($ts);
    }

    /**
     * Format minutes as hours decimal (e.g. 90 => "1.50").
     */
    private function formatMinutesHuman(int $minutes): string
    {
        return number_format(max(0, $minutes) / 60, 2, '.', '');
    }

    /**
     * Calculate expected work minutes from check-in and check-out times.
     */
    private function calculateExpectedWorkMinutesFromTimes(?string $checkIn, ?string $checkOut): int
    {
        if (! $checkIn || ! $checkOut) {
            return 0;
        }

        $inMinutes = $this->parseTimeToMinutes($checkIn);
        $outMinutes = $this->parseTimeToMinutes($checkOut);

        if ($inMinutes === null || $outMinutes === null) {
            return 0;
        }

        $expectedMinutes = $outMinutes - $inMinutes;

        // Handle overnight shifts (e.g., check-in 22:00, check-out 06:00)
        if ($expectedMinutes < 0) {
            $expectedMinutes += 24 * 60;
        }

        return $expectedMinutes;
    }

    /**
     * Calculate actual work minutes from actual check-in and check-out datetimes.
     */
    private function calculateActualWorkMinutes(?string $checkIn, ?string $checkOut): int
    {
        if (! $checkIn || ! $checkOut) {
            return 0;
        }

        $inTime = strtotime($checkIn);
        $outTime = strtotime($checkOut);

        if ($inTime === false || $outTime === false) {
            return 0;
        }

        $diffMinutes = (int) round(($outTime - $inTime) / 60);

        return max(0, $diffMinutes);
    }

    /**
     * Extract an H:i display string from a datetime string.
     *
     * Handles both full datetime strings ("2026-07-01 08:00") and
     * already-formatted time strings ("08:00").
     */
    private function formatTimeForDisplay(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        // Extract H:i from "Y-m-d H:i:s" or "Y-m-d H:i" datetime strings.
        if (preg_match('/(\d{2}:\d{2})/', $value, $m)) {
            return $m[1];
        }

        return null;
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
                ->whereHas('user', fn ($query) => $query
                    ->withoutSuperAdmin()
                    ->active())
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
