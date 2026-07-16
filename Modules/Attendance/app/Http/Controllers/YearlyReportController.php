<?php

namespace Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Services\YearlyReportService;

/**
 * YearlyReportController — calendar-year roll-ups.
 */
class YearlyReportController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private YearlyReportService $yearlyService,
    ) {}

    /**
     * Display the yearly report for the supplied year.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-attendance');

        $year = (int) $request->input('year', (int) date('Y'));

        $kpis = $this->yearlyService->getYearlyKpis($year);
        $months = $this->yearlyService->getYearlyMonthlyBreakdown($year);
        $users = $this->yearlyService->getUserYearlyReport($year);
        $departments = $this->yearlyService->getDepartmentYearlyReport($year);

        return Inertia::render('Attendance/Reports/Yearly', [
            'filters' => fn () => $this->cleanFilters($request->only(['year'])),
            'kpis' => fn () => $kpis,
            'months' => fn () => $months,
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
