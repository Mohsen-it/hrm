<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\DailyAttendanceSummary;
use Modules\Attendance\Services\AttendanceMonitoringService;
use Modules\Attendance\Services\AttendanceReportService;
use Modules\Attendance\Services\MonthlyReportService;
use Modules\AttendanceIntegration\Services\LivePunchFeedService;
use Modules\Departments\Models\Department;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;
use Modules\Shifts\Models\Shift;
use Modules\Shifts\Services\AbsenceCalculationService;
use Modules\Users\Models\User;
use Modules\Vacations\Models\UserVacationRequest;

class DashboardController extends Controller
{
    private const STATS_CACHE_TTL = 30;

    private const RECENT_CACHE_TTL = 5;

    private const PULL_LOCK_TTL = 10;

    /** Prevent device I/O from being triggered repeatedly by dashboard polling. */
    private const PULL_COOLDOWN = 300;

    public function __construct(
        private LivePunchFeedService $livePunchProcessor,
        private FingerprintDeviceService $deviceService,
        private AttendanceMonitoringService $monitoringService,
        private AttendanceReportService $reportService,
        private MonthlyReportService $monthlyReportService,
        private AbsenceCalculationService $absenceCalculationService,
    ) {}

    /**
     * Display the operational control center dashboard.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-dashboard');

        $today = $this->resolveDashboardDate($request);

        return Inertia::render('Dashboard', [
            'title' => __('menu.dashboard'),
            'dashboard' => fn () => $this->getDashboardData($today),
            'recentAttendance' => fn () => $this->getRecentAttendance()['merged'],
        ]);
    }

    /**
     * Lightweight JSON endpoint for dashboard polling.
     */
    public function snapshot(Request $request): JsonResponse
    {
        $this->authorize('view-dashboard');

        $today = $this->resolveDashboardDate($request);

        return response()->json([
            'dashboard' => $this->getDashboardData($today),
            'recentAttendance' => $this->getRecentAttendance()['merged'],
        ]);
    }

    /**
     * Lightweight endpoint that returns only the cached live punch feed.
     */
    public function pullEvents(Request $request): JsonResponse
    {
        $this->authorize('view-dashboard');

        $payload = $this->getRecentAttendance(20);

        $response = [
            'events' => $payload['merged'],
            'devices_polled' => 0,
            'errors' => [],
            'server_time' => now()->toDateTimeString(),
        ];

        if ($request->boolean('sync') && Cache::lock('dashboard:pull-sync', self::PULL_LOCK_TTL)->get()) {
            try {
                $devices = FingerprintDevice::query()
                    ->where('status', 'online')
                    ->get();

                $cooldownKey = 'dashboard:pull-last';
                $lastPull = Cache::get($cooldownKey);
                $shouldSync = $lastPull === null || (time() - (int) $lastPull) >= self::PULL_COOLDOWN;

                $errors = [];
                $polled = 0;

                if ($shouldSync) {
                    Cache::put($cooldownKey, time(), 60);

                    foreach ($devices as $device) {
                        try {
                            $this->deviceService->syncAttendance($device);
                            $polled++;
                        } catch (\Throwable $e) {
                            $errors[] = [
                                'device' => $device->name,
                                'error' => $e->getMessage(),
                            ];
                            Log::warning('Dashboard pullEvents: failed for device', [
                                'device_id' => $device->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    Cache::forget('dashboard:recent_attendance');
                }

                $response['events'] = $this->getRecentAttendance(20)['merged'];
                $response['devices_polled'] = $polled;
                $response['errors'] = $errors;
                $response['sync_performed'] = $shouldSync;
            } finally {
                Cache::lock('dashboard:pull-sync')->release();
            }
        }

        return response()->json($response);
    }

    /**
     * Gather all dashboard data (cached where appropriate).
     */
    private function getDashboardData(string $today): array
    {
        return Cache::remember('dashboard:full_data:'.$today, self::STATS_CACHE_TTL, function () use ($today): array {
            // Use a single query for active user IDs (needed for fingerprint check)
            $activeUserIds = User::query()
                ->where('id', '!=', User::SUPER_ADMIN_ID)
                ->where('status', 1)
                ->where('is_active_employee', true)
                ->pluck('id')
                ->all();

            $employees = count($activeUserIds);

            // Today's headline figures must be calculated from the live
            // rotation roster, approved exceptions, and real punches. Daily
            // summaries are asynchronous and are retained for history only.
            $operationalSnapshot = $this->absenceCalculationService
                ->getOperationalSnapshot(CarbonImmutable::parse($today));
            $dailyKpis = [
                'date' => $today,
                'present' => $operationalSnapshot['present'],
                'absent' => $operationalSnapshot['absent'],
                'late' => $operationalSnapshot['late'],
                'early_leave' => $operationalSnapshot['early_leave'],
                'missing_punch' => $operationalSnapshot['missing_punch'],
                'total' => $operationalSnapshot['required'],
                'scheduled' => $operationalSnapshot['scheduled'],
                'awaiting_arrival' => $operationalSnapshot['awaiting_arrival'],
                'by_status' => $operationalSnapshot['by_status'],
            ];

            // Live sessions (currently inside) — fetch once, reuse for health
            $liveSessions = $this->monitoringService->getLiveSessions($today);
            $currentlyInside = $liveSessions->count();

            // "Outside" is only meaningful for today. It is the verified set
            // of people who checked in and subsequently checked out, not a
            // subtraction that can be distorted by late/early-leave summaries.
            $currentlyOutside = $today === CarbonImmutable::now()->toDateString()
                ? (int) AttendanceSession::onDate($today)
                    ->whereIn('user_id', $activeUserIds)
                    ->whereNotNull('check_in_at')
                    ->whereNotNull('check_out_at')
                    ->distinct('user_id')
                    ->count('user_id')
                : 0;

            // These values come from the same daily roster as presence and
            // absence, preventing overlaps (e.g. vacation + mirrored leave)
            // from being counted twice.
            $onLeave = $operationalSnapshot['on_leave'];

            // Pending requests
            $pendingRequests = (int) UserVacationRequest::pending()->count();

            $onMission = $operationalSnapshot['on_mission'];

            // Late employees today
            $lateToday = $dailyKpis['late'] ?? 0;

            // Absent today
            $absentToday = $dailyKpis['absent'] ?? 0;

            // Missing fingerprints — use COUNT subquery instead of loading all IDs
            $missingFingerprints = $employees > 0
                ? $employees - (int) UserFingerprint::whereIn('user_id', $activeUserIds)->distinct('user_id')->count('user_id')
                : 0;

            // Active devices
            $activeDevices = (int) FingerprintDevice::where('status', 'online')->count();
            $totalDevices = (int) FingerprintDevice::where('status', '!=', 'deactivated')->count();

            // System health — pass pre-fetched liveSessions to avoid duplicate query
            $health = $this->monitoringService->getHealthSnapshot($today, $liveSessions);

            // Weekly trend (last 7 days)
            $weekFrom = CarbonImmutable::parse($today)->subDays(6)->toDateString();
            $weeklyTrend = $this->reportService->getDailyTrend($weekFrom, $today);

            // Monthly trend (last 30 days)
            $monthFrom = CarbonImmutable::parse($today)->subDays(29)->toDateString();
            $monthlyTrend = $this->reportService->getDailyTrend($monthFrom, $today);

            // Department comparison (last 7 days)
            $departmentStats = $this->reportService->getDepartmentComparison($weekFrom, $today);

            // Monthly KPIs
            $monthlyKpis = $this->monthlyReportService->getMonthlyKpis(
                (int) CarbonImmutable::parse($today)->year,
                (int) CarbonImmutable::parse($today)->month,
            );

            // Top late employees (last 7 days)
            $topLate = $this->reportService->getTopLateEmployees($weekFrom, $today, 5);

            // Shift overview
            $shiftOverview = $this->getShiftOverview($today);
            $workforceBreakdown = $this->getWorkforceBreakdown($today);

            // Recent approvals (last 10 processed vacation requests)
            $recentApprovals = UserVacationRequest::with(['user', 'vacationType', 'manager'])
                ->whereIn('status', ['approved', 'rejected'])
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'employee_name' => $r->user?->full_name ?? $r->user?->name,
                    'employee_code' => $r->user?->employee_code,
                    'type' => $r->vacationType?->name_en ?? $r->vacationType?->name_ar,
                    'status' => $r->status,
                    'start_date' => $r->start_date?->format('Y-m-d'),
                    'end_date' => $r->end_date?->format('Y-m-d'),
                    'manager_name' => $r->manager?->name,
                    'updated_at' => $r->updated_at?->toIso8601String(),
                ]);

            // Recent fingerprint syncs
            $recentSyncs = FingerprintDevice::whereNotNull('last_synced_at')
                ->orderByDesc('last_synced_at')
                ->limit(5)
                ->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'status' => $d->status,
                    'last_synced_at' => $d->last_synced_at?->toIso8601String(),
                    'attendance_log_count' => $d->attendance_log_count,
                ]);

            // Attendance anomalies
            $anomalies = $this->monitoringService->getAnomalies($today, 10)
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'employee_name' => $a->user?->full_name ?? $a->user?->name,
                    'employee_code' => $a->user?->employee_code,
                    'status' => $a->status,
                    'late_minutes' => $a->late_minutes,
                    'first_check_in' => $a->first_check_in_at?->format('H:i'),
                    'last_check_out' => $a->last_check_out_at?->format('H:i'),
                ]);

            // Mass detection
            $massLateness = $this->buildOperationalAlert(
                $today,
                $operationalSnapshot['late'],
                $operationalSnapshot['required'],
                0.30,
                'late_count',
            );
            $massAbsence = $this->buildOperationalAlert(
                $today,
                $operationalSnapshot['absent'],
                $operationalSnapshot['required'],
                0.25,
                'absent_count',
            );

            // Attendance heatmap (last 30 days)
            $heatmapData = $this->getHeatmapData($monthFrom, $today);

            // Live counters for animation
            $liveCounters = [
                'employees' => $employees,
                'required' => $operationalSnapshot['required'],
                'present' => $dailyKpis['present'] ?? 0,
                'absent' => $absentToday,
                'awaiting_arrival' => $operationalSnapshot['awaiting_arrival'],
                'late' => $lateToday,
                'early_leave' => $dailyKpis['early_leave'] ?? 0,
                'missing_punch' => $dailyKpis['missing_punch'] ?? 0,
                'inside' => $currentlyInside,
                'outside' => $currentlyOutside,
                'on_leave' => $onLeave,
                'on_mission' => $onMission,
                'pending_requests' => $pendingRequests,
                'active_devices' => $activeDevices,
                'missing_fingerprints' => $missingFingerprints,
            ];

            return [
                'today' => $today,
                'liveCounters' => $liveCounters,
                'dailyKpis' => $dailyKpis,
                'operationalSnapshot' => $operationalSnapshot,
                'weeklyTrend' => $weeklyTrend,
                'monthlyTrend' => $monthlyTrend,
                'departmentStats' => $departmentStats,
                'monthlyKpis' => $monthlyKpis,
                'topLate' => $topLate,
                'shiftOverview' => $shiftOverview,
                'workforceBreakdown' => $workforceBreakdown,
                'recentApprovals' => $recentApprovals,
                'recentSyncs' => $recentSyncs,
                'anomalies' => $anomalies,
                'health' => $health,
                'heatmapData' => $heatmapData,
                'massLateness' => $massLateness,
                'massAbsence' => $massAbsence,
                'activeDevices' => $activeDevices,
                'totalDevices' => $totalDevices,
            ];
        });
    }

    /**
     * Resolve the requested reporting date without accepting malformed input.
     */
    private function resolveDashboardDate(Request $request): string
    {
        $date = $request->query('date');

        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return CarbonImmutable::now()->toDateString();
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $date)->toDateString();
        } catch (\Throwable) {
            return CarbonImmutable::now()->toDateString();
        }
    }

    /**
     * Build a dashboard alert from the operational roster denominator.
     *
     * @return array{date: string, is_alert: bool, late_count?: int, absent_count?: int, total: int, ratio: float, threshold: float}
     */
    private function buildOperationalAlert(
        string $date,
        int $count,
        int $total,
        float $threshold,
        string $countKey,
    ): array {
        $ratio = $total > 0 ? round($count / $total, 4) : 0.0;

        return [
            'date' => $date,
            $countKey => $count,
            'total' => $total,
            'ratio' => $ratio,
            'threshold' => $threshold,
            'is_alert' => $total > 0 && $ratio >= $threshold,
        ];
    }

    /**
     * Provide a current, de-duplicated view of the workforce configuration.
     *
     * These counts intentionally use employee assignments that are valid on
     * the selected date, so an expired rotation/category is never presented
     * as an active operational assignment.
     *
     * @return array<string, array<int, array<string, int|string|null>>>
     */
    private function getWorkforceBreakdown(string $date): array
    {
        $activeEmployees = fn ($query) => $query
            ->where('users.id', '!=', User::SUPER_ADMIN_ID)
            ->where('users.status', 1)
            ->where('users.is_active_employee', true);

        $grades = $activeEmployees(DB::table('users'))
            ->leftJoin('grades', 'grades.id', '=', 'users.grade_id')
            ->groupBy('users.grade_id', 'grades.grade_name', 'grades.level')
            ->orderBy('grades.level')
            ->selectRaw('users.grade_id as id, grades.grade_name as name, grades.level, COUNT(*) as employees')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id ? (int) $row->id : 0,
                'name' => $row->name,
                'level' => $row->level !== null ? (int) $row->level : null,
                'employees' => (int) $row->employees,
            ])->all();

        $categories = $activeEmployees(DB::table('users'))
            ->join('att_employee_shift_categories as assignments', function ($join) use ($date) {
                $join->on('assignments.employee_id', '=', 'users.id')
                    ->where('assignments.start_date', '<=', $date)
                    ->where(fn ($query) => $query->whereNull('assignments.end_date')->orWhere('assignments.end_date', '>=', $date));
            })
            ->join('att_shift_categories as categories', 'categories.id', '=', 'assignments.shift_category_id')
            ->groupBy('categories.id', 'categories.name', 'categories.type')
            ->orderBy('categories.name')
            ->selectRaw('categories.id, categories.name, categories.type, COUNT(DISTINCT users.id) as employees')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'type' => $row->type,
                'employees' => (int) $row->employees,
            ])->all();

        $rotations = $activeEmployees(DB::table('users'))
            ->join('att_rotation_assignments as assignments', function ($join) use ($date) {
                $join->on('assignments.employee_id', '=', 'users.id')
                    ->where('assignments.start_date', '<=', $date)
                    ->where(fn ($query) => $query->whereNull('assignments.end_date')->orWhere('assignments.end_date', '>=', $date));
            })
            ->join('att_rotations as rotations', 'rotations.id', '=', 'assignments.rotation_id')
            ->groupBy('rotations.id', 'rotations.name')
            ->orderBy('rotations.name')
            ->selectRaw('rotations.id, rotations.name, COUNT(DISTINCT users.id) as employees')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'employees' => (int) $row->employees,
            ])->all();

        return compact('grades', 'categories', 'rotations');
    }

    /**
     * Get shift overview for today.
     */
    private function getShiftOverview(string $today): array
    {
        $shifts = Shift::where('status', 1)
            ->withCount(['users' => fn ($q) => $q->where('status', 1)->where('is_active_employee', true)])
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->shift_name,
                'code' => $s->shift_code,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
                'employee_count' => $s->users_count,
            ])
            ->toArray();

        // Upcoming shifts (next 3 days)
        $upcoming = [];
        for ($i = 1; $i <= 3; $i++) {
            $date = CarbonImmutable::parse($today)->addDays($i);
            $dayName = $date->locale(app()->getLocale() === 'ar' ? 'ar' : 'en')->isoFormat('dddd');
            $upcoming[] = [
                'date' => $date->toDateString(),
                'day_name' => $dayName,
                'is_weekend' => in_array($date->isoFormat('dddd'), ['Friday', 'Saturday']),
            ];
        }

        return [
            'shifts' => $shifts,
            'upcoming' => $upcoming,
        ];
    }

    /**
     * Get attendance heatmap data for a date range.
     */
    private function getHeatmapData(string $from, string $to): array
    {
        $cacheKey = 'dashboard:heatmap:'.$from.':'.$to;

        return Cache::remember($cacheKey, self::STATS_CACHE_TTL, function () use ($from, $to): array {
            $rows = DailyAttendanceSummary::betweenDates($from, $to)
                ->groupBy('summary_date')
                ->orderBy('summary_date')
                ->selectRaw('
                    summary_date,
                    SUM(CASE WHEN status IN ("present", "late", "early_leave") THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late,
                    COUNT(*) as total
                ')
                ->get();

            return $rows->map(fn ($row) => [
                'date' => $row->summary_date->format('Y-m-d'),
                'present' => (int) $row->present,
                'absent' => (int) $row->absent,
                'late' => (int) $row->late,
                'total' => (int) $row->total,
                // A missing rate means there are no records for the day. Keep a
                // genuine 0% rate visible as the lowest heatmap colour instead
                // of treating it as an empty (silver) cell.
                'rate' => $row->total > 0 ? round(($row->present / $row->total) * 100, 1) : null,
            ])->all();
        });
    }

    /**
     * Fetch the most recent attendance sessions for the dashboard table (cached).
     */
    private function getRecentAttendance(int $limit = 20): array
    {
        $cacheKey = 'dashboard:recent_attendance';

        return Cache::remember($cacheKey, self::RECENT_CACHE_TTL, function () use ($limit): array {
            return ['merged' => $this->buildRecentAttendance($limit)];
        });
    }

    /**
     * Build the merged live + DB recent attendance list (no cache).
     */
    private function buildRecentAttendance(int $limit = 20): array
    {
        $livePunches = $this->livePunchProcessor->getRecentPunches(50);

        $sessions = AttendanceSession::with(['user', 'device'])
            ->orderByDesc('check_in_at')
            ->limit($limit)
            ->get()
            ->map(fn ($s) => [
                'id' => 'session_'.$s->id,
                'source' => 'session',
                'session_id' => $s->id,
                'employee_name' => $s->user?->full_name ?? $s->user?->name ?? '#'.$s->user_id,
                'employee_code' => $s->user?->employee_code ?? '',
                'avatar_url' => $s->user?->avatar_url,
                'device_name' => $s->device?->name ?? '',
                'time' => $s->check_in_at?->format('H:i:s') ?? '—',
                'check_in_at' => $s->check_in_at?->toIso8601String(),
                'type' => $s->check_out_at ? 'خروج' : 'دخول',
                'status' => $s->check_out_at ? 'check_out' : 'check_in',
            ]);

        $liveMapped = collect($livePunches)->map(fn ($p) => [
            'id' => 'live_'.$p['session_id'],
            'source' => 'live',
            'session_id' => $p['session_id'],
            'employee_name' => $p['user']['name'] ?? '',
            'employee_code' => $p['user']['employee_code'] ?? '',
            'avatar_url' => null,
            'device_name' => $p['device']['name'] ?? '',
            'time' => isset($p['punched_at']) ? Carbon::parse($p['punched_at'])->format('H:i:s') : '—',
            'check_in_at' => $p['punched_at'] ?? null,
            'type' => $p['punch_type'] === 'check_in' ? 'دخول' : 'خروج',
            'status' => $p['punch_type'],
        ]);

        $seenSessionIds = [];
        $merged = collect();

        foreach ($liveMapped as $item) {
            $sid = $item['session_id'];
            if (! in_array($sid, $seenSessionIds, true)) {
                $seenSessionIds[] = $sid;
                $merged->push($item);
            }
        }

        foreach ($sessions as $item) {
            $sid = $item['session_id'];
            if (! in_array($sid, $seenSessionIds, true)) {
                $seenSessionIds[] = $sid;
                $merged->push($item);
            }
        }

        return $merged->take($limit)->values()->all();
    }
}
