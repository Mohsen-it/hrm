<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Shifts\Exports\SmartAbsenceDailyExport;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Repositories\RotationRepository;
use Modules\Shifts\Services\AbsenceCalculationService;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SmartAbsenceController extends Controller
{
    public function __construct(
        private AbsenceCalculationService $absenceService,
        private RotationRepository $rotationRepository,
        private RotationAssignmentRepository $rotationAssignmentRepository,
    ) {}

    /**
     * Daily smart absence report.
     */
    public function daily(Request $request): Response
    {
        $this->authorize('view-attendance-by-schedule');

        $date = $request->input('date') ? Carbon::parse($request->input('date')) : now();
        $dateStr = $date->toDateString();
        $departmentId = $request->input('department_id') ? (int) $request->input('department_id') : null;
        $rotationId = $request->input('rotation_id') ? (int) $request->input('rotation_id') : null;
        $rotationGroupId = $request->input('rotation_group_id') ? (int) $request->input('rotation_group_id') : null;

        $report = $this->buildDailyReport($date, $dateStr, $departmentId, $rotationId, $rotationGroupId);

        $rotations = $this->rotationRepository->getAllList()->map(fn ($rotation) => [
            'id' => $rotation->id,
            'name' => $rotation->name,
            'groups' => $rotation->groups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
            ])->toArray(),
        ])->toArray();

        return Inertia::render('Shifts/Absence/SmartAbsenceReport', [
            'dailyData' => [
                'date' => $dateStr,
                'expected' => $report['expected']->toArray(),
                'absent' => $report['absentDetails'],
                'total_expected' => $report['expected']->count(),
                'total_absent' => $report['absent']->count(),
            ],
            'rotations' => $rotations,
            'monthlyData' => [],
            'filters' => [
                'department_id' => $departmentId,
                'rotation_id' => $rotationId,
                'rotation_group_id' => $rotationGroupId,
                'date' => $dateStr,
            ],
        ]);
    }

    /**
     * Export the daily smart-absence report as a fully-formatted .xlsx file
     * with Arabic / RTL support.
     */
    public function exportDaily(Request $request): HttpResponse
    {
        $this->authorize('view-attendance-by-schedule');

        $date = $request->input('date') ? Carbon::parse($request->input('date')) : now();
        $dateStr = $date->toDateString();
        $departmentId = $request->input('department_id') ? (int) $request->input('department_id') : null;
        $rotationId = $request->input('rotation_id') ? (int) $request->input('rotation_id') : null;
        $rotationGroupId = $request->input('rotation_group_id') ? (int) $request->input('rotation_group_id') : null;

        $report = $this->buildDailyReport($date, $dateStr, $departmentId, $rotationId, $rotationGroupId);

        $export = new SmartAbsenceDailyExport(
            date: $date,
            totalExpected: $report['expected']->count(),
            totalAbsent: $report['absent']->count(),
            absentDetails: $report['absentDetails'],
            statusLabel: __('shifts.absent_short', [], null) ?: 'غياب',
        );

        $fileName = "smart-absence-{$dateStr}.xlsx";
        $content = $export->toBinary();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"; filename*=UTF-8''" . rawurlencode($fileName),
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Build the daily report data (expected, absent, enriched details) for a given
     * date/filter set. Shared by the page render and the Excel export.
     *
     * @return array{expected: \Illuminate\Support\Collection<int, int>, absent: \Illuminate\Support\Collection<int, int>, absentDetails: \Illuminate\Support\Collection}
     */
    private function buildDailyReport(
        Carbon $date,
        string $dateStr,
        ?int $departmentId,
        ?int $rotationId,
        ?int $rotationGroupId,
    ): array {
        $expected = $this->absenceService->getExpectedEmployees($date, $departmentId, $rotationId, $rotationGroupId);
        $absent = $this->absenceService->getAbsentEmployees($date, $departmentId, $rotationId, $rotationGroupId);

        $absentDetails = collect();
        if ($absent->isNotEmpty()) {
            $absentDetails = DB::table('users')
                ->whereIn('users.id', $absent->toArray())
                ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                ->get([
                    'users.id',
                    'users.name',
                    'users.employee_code',
                    'users.department_id',
                    'departments.department_name',
                ]);

            $absentAssignments = $this->rotationAssignmentRepository->getAssignmentsForDate($dateStr);

            $absentDetails = $absentDetails->map(function ($row) use ($absentAssignments) {
                $assignment = $absentAssignments->firstWhere('employee_id', $row->id);
                $row->rotation_name = $assignment?->rotation?->name;
                $row->rotation_group_name = $assignment?->rotationGroup?->name;

                return $row;
            })->values();
        }

        return [
            'expected' => $expected,
            'absent' => $absent,
            'absentDetails' => $absentDetails,
        ];
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
