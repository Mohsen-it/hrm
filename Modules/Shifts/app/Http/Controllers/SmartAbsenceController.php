<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Shifts\Services\AbsenceCalculationService;

class SmartAbsenceController extends Controller
{
    public function __construct(
        private AbsenceCalculationService $absenceService
    ) {}

    /**
     * Daily smart absence report.
     */
    public function daily(?string $date = null, ?int $departmentId = null): Response
    {
        $this->authorize('view-attendance-by-schedule');

        $date = $date ? Carbon::parse($date) : now();
        $dateStr = $date->toDateString();

        $expected = $this->absenceService->getExpectedEmployees($date);
        $absent = $this->absenceService->getAbsentEmployees($date, $departmentId);

        $absentDetails = DB::table('users')
            ->whereIn('id', $absent->toArray())
            ->get(['id', 'name', 'employee_code', 'department_id']);

        return Inertia::render('Shifts/Absence/SmartAbsenceReport', [
            'dailyData' => [
                'date' => $dateStr,
                'expected' => $expected->toArray(),
                'absent' => $absentDetails,
                'total_expected' => $expected->count(),
                'total_absent' => $absent->count(),
            ],
            'monthlyData' => [],
            'filters' => [
                'department_id' => $departmentId,
                'date' => $dateStr,
            ],
        ]);
    }

    /**
     * Monthly absence for a specific employee.
     */
    public function monthly(int $employeeId, ?int $month = null, ?int $year = null): Response
    {
        $this->authorize('view-attendance-by-schedule');

        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $monthlyData = $this->absenceService->getMonthlyAbsence($employeeId, $month, $year);

        $employee = DB::table('users')->find($employeeId);

        return Inertia::render('Shifts/Absence/SmartAbsenceReport', [
            'dailyData' => [],
            'monthlyData' => $monthlyData,
            'filters' => [
                'employee_id' => $employeeId,
                'employee_name' => $employee?->name,
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }

    /**
     * Team absence - filters by team.
     */
    public function teamAbsence(Request $request): Response
    {
        $user = auth()->user();
        $teamIds = DB::table('users')
            ->where('manager_id', $user->id)
            ->pluck('id')
            ->toArray();

        $date = $request->input('date') ? Carbon::parse($request->input('date')) : now();
        $dateStr = $date->toDateString();

        $absentTeam = $this->absenceService->getAbsentEmployees($date);
        $absentTeam = $absentTeam->intersect($teamIds)->values();

        $absentDetails = DB::table('users')
            ->whereIn('id', $absentTeam->toArray())
            ->get(['id', 'name', 'employee_code', 'department_id']);

        return Inertia::render('Shifts/Absence/TeamAbsence', [
            'date' => $dateStr,
            'absent' => $absentDetails,
            'total_absent' => $absentTeam->count(),
        ]);
    }

    /**
     * My absence - for logged-in user.
     */
    public function myAbsence(Request $request): Response
    {
        $userId = auth()->id();
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $monthlyData = $this->absenceService->getMonthlyAbsence($userId, (int) $month, (int) $year);

        return Inertia::render('Shifts/Absence/MyAbsence', [
            'monthlyData' => $monthlyData,
            'month' => (int) $month,
            'year' => (int) $year,
        ]);
    }
}
