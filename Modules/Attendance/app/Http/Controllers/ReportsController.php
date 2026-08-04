<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ExcelExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Exports\AttendanceReportExport;
use Modules\Attendance\Exports\MonthlyEmployeeAttendanceLogExport;
use Modules\Attendance\Http\Requests\MonthlyEmployeeAttendanceLogRequest;
use Modules\Attendance\Services\AttendanceReportService;
use Modules\Attendance\Services\MonthlyEmployeeAttendanceLogService;
use Modules\Users\Services\UserService;

/**
 * ReportsController — ad-hoc, range-based reports (per user, per department,
 * daily KPIs, daily trend, top late list).
 */
class ReportsController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private AttendanceReportService $reportService,
        private MonthlyEmployeeAttendanceLogService $monthlyEmployeeLogService,
        private UserService $userService,
        private ExcelExportService $excelExporter,
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
    public function userReport(MonthlyEmployeeAttendanceLogRequest $request, int $userId): Response
    {
        $this->authorize('view-attendance');

        $from = (string) $request->input('from', now()->subDays(30)->toDateString());
        $to = (string) $request->input('to', now()->toDateString());

        $report = $this->reportService->getUserReport($userId, $from, $to);
        $overtime = $this->reportService->getUserOvertimeReport($userId, $from, $to);
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $monthlyLog = $this->monthlyEmployeeLogService->getMonthlyLog($userId, $year, $month);

        return Inertia::render('Attendance/Reports/User', [
            'userId' => fn () => $userId,
            'filters' => fn () => $this->cleanFilters($request->only(['from', 'to', 'year', 'month'])),
            'report' => fn () => $report,
            'overtime' => fn () => $overtime,
            'monthlyLog' => fn () => $monthlyLog,
            'monthlyLogFilters' => fn () => ['year' => $year, 'month' => $month],
        ]);
    }

    /**
     * Download the employee's schedule-window-aware monthly log as Excel.
     */
    public function exportMonthlyLog(MonthlyEmployeeAttendanceLogRequest $request, int $userId)
    {
        $this->authorize('view-attendance');

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $employee = $this->userService->getUserById($userId);
        $monthDate = now()->setDate($year, $month, 1);
        $export = new MonthlyEmployeeAttendanceLogExport(
            $this->excelExporter,
            $employee?->name ?? (string) $userId,
            $monthDate->translatedFormat('F Y'),
            $this->monthlyEmployeeLogService->getMonthlyLog($userId, $year, $month),
        );

        return response($this->excelExporter->toBinary($export->build()), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="employee-attendance-'.$userId.'-'.$monthDate->format('Y-m').'.xlsx"',
            'Cache-Control' => 'max-age=0',
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

        return response($this->excelExporter->toBinary($export->build()), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="attendance-report-'.$from.'_'.$to.'.xlsx"',
            'Cache-Control' => 'max-age=0',
        ]);
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
