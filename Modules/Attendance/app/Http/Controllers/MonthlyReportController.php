<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Services\MonthlyReportService;

/**
 * MonthlyReportController — calendar-month roll-ups.
 */
class MonthlyReportController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private MonthlyReportService $monthlyService,
    ) {}

    /**
     * Display the monthly report for the supplied year / month.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-attendance');

        $year = (int) $request->input('year', (int) date('Y'));
        $month = (int) $request->input('month', (int) date('n'));

        $kpis = $this->monthlyService->getMonthlyKpis($year, $month);
        $breakdown = $this->monthlyService->getMonthlyDailyBreakdown($year, $month);
        $users = $this->monthlyService->getUserMonthlyReport($year, $month);
        $departments = $this->monthlyService->getDepartmentMonthlyReport($year, $month);

        return Inertia::render('Attendance/Reports/Monthly', [
            'filters' => fn () => $this->cleanFilters($request->only(['year', 'month'])),
            'kpis' => fn () => $kpis,
            'breakdown' => fn () => $breakdown,
            'users' => fn () => $users,
            'departments' => fn () => $departments,
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
