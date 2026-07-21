<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ExcelExportable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Exports\AttendanceReportExport;
use Modules\Attendance\Services\AttendanceReportService;
use Modules\Users\Services\UserService;

/**
 * ReportsController — ad-hoc, range-based reports (per user, per department,
 * daily KPIs, daily trend, top late list).
 */
class ReportsController extends Controller
{
    use ExcelExportable;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        private AttendanceReportService $reportService,
        private UserService $userService,
    ) {}

    /**
     * Display the ad-hoc reports landing page.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-attendance');

        $from = (string) $request->input('from', now()->subDays(7)->toDateString());
        $to = (string) $request->input('to', now()->toDateString());
        $date = (string) $request->input('date', now()->toDateString());

        $dailyKpis = $this->reportService->getDailyKpis($date);
        $dailyTrend = $this->reportService->getDailyTrend($from, $to);
        $deptComparison = $this->reportService->getDepartmentComparison($from, $to);
        $topLate = $this->reportService->getTopLateEmployees($from, $to, 10);

        return Inertia::render('Attendance/Reports/Index', [
            'filters' => fn () => $this->cleanFilters($request->only(['from', 'to', 'date'])),
            'kpis' => fn () => $dailyKpis,
            'trend' => fn () => $dailyTrend,
            'departmentComparison' => fn () => $deptComparison,
            'topLate' => fn () => $topLate,
        ]);
    }

    /**
     * Per-user report inside a date range.
     */
    public function userReport(Request $request, int $userId): Response
    {
        $this->authorize('view-attendance');

        $from = (string) $request->input('from', now()->subDays(30)->toDateString());
        $to = (string) $request->input('to', now()->toDateString());

        $report = $this->reportService->getUserReport($userId, $from, $to);
        $overtime = $this->reportService->getUserOvertimeReport($userId, $from, $to);

        return Inertia::render('Attendance/Reports/User', [
            'userId' => fn () => $userId,
            'filters' => fn () => $this->cleanFilters($request->only(['from', 'to'])),
            'report' => fn () => $report,
            'overtime' => fn () => $overtime,
        ]);
    }

    /**
     * Export the ad-hoc attendance report to Excel.
     */
    public function export(Request $request)
    {
        $this->authorize('view-attendance');

        $from = (string) $request->input('from', now()->subDays(7)->toDateString());
        $to = (string) $request->input('to', now()->toDateString());
        $date = (string) $request->input('date', now()->toDateString());

        $export = new AttendanceReportExport(
            fromDate: $from,
            toDate: $to,
            date: $date,
            kpis: $this->reportService->getDailyKpis($date),
            trend: $this->reportService->getDailyTrend($from, $to),
            departmentComparison: $this->reportService->getDepartmentComparison($from, $to),
            topLate: $this->reportService->getTopLateEmployees($from, $to, 10),
        );

        return $this->downloadExcel($export->build(), 'attendance-report-'.$from.'_'.$to);
    }

    /**
     * Drop empty / null entries from a filter bag so the URL stays clean.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function cleanFilters(array $filters): array
    {
        return array_filter(
            $filters,
            fn ($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}
