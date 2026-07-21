<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $rotationIds = $this->parseIdList($request->input('rotation_ids', $request->input('rotation_id')));
        $rotationGroupIds = $this->parseIdList($request->input('rotation_group_ids', $request->input('rotation_group_id')));

        $report = $this->buildDailyReport($date, $dateStr, $departmentId, $rotationIds, $rotationGroupIds);

        $rotations = $this->rotationRepository->getAllList()->map(fn ($rotation) => [
            'id' => $rotation->id,
            'name' => $rotation->name,
            'groups' => $rotation->groups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
            ])->toArray(),
        ])->toArray();

        $departments = DB::table('departments')
            ->where('status', 1)
            ->orderBy('department_name')
            ->get(['id', 'department_name'])
            ->map(fn ($dept) => ['id' => (int) $dept->id, 'name' => $dept->department_name])
            ->all();

        $totalExpected = $report['expected']->count();
        $totalAbsent = $report['absent']->count();
        $attendanceRate = $totalExpected > 0
            ? (int) round((($totalExpected - $totalAbsent) / $totalExpected) * 100)
            : 100;

        return Inertia::render('Shifts/Absence/SmartAbsenceReport', [
            'dailyData' => [
                'date' => $dateStr,
                'expected' => $report['expected']->toArray(),
                'absent' => $report['absentDetails'],
                'total_expected' => $totalExpected,
                'total_absent' => $totalAbsent,
                'attendance_rate' => $attendanceRate,
            ],
            'rotations' => $rotations,
            'departments' => $departments,
            'monthlyData' => [],
            'filters' => [
                'department_id' => $departmentId,
                'rotation_ids' => $rotationIds,
                'rotation_group_ids' => $rotationGroupIds,
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
        $rotationIds = $this->parseIdList($request->input('rotation_ids', $request->input('rotation_id')));
        $rotationGroupIds = $this->parseIdList($request->input('rotation_group_ids', $request->input('rotation_group_id')));

        $report = $this->buildDailyReport($date, $dateStr, $departmentId, $rotationIds, $rotationGroupIds);

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
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"; filename*=UTF-8''".rawurlencode($fileName),
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Parse a request value that may be an int, a CSV string, or an array of ids.
     *
     * @return array<int, int>
     */
    private function parseIdList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $raw = is_array($value) ? $value : explode(',', (string) $value);

        return collect($raw)
            ->filter(fn ($id) => $id !== null && $id !== '' && $id !== false)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Build the daily report data (expected, absent, enriched details) for a given
     * date/filter set. Shared by the page render and the Excel export.
     *
     * @param  array<int, int>  $rotationIds
     * @param  array<int, int>  $rotationGroupIds
     * @return array{expected: Collection<int, int>, absent: Collection<int, int>, absentDetails: Collection}
     */
    private function buildDailyReport(
        Carbon $date,
        string $dateStr,
        ?int $departmentId,
        array $rotationIds,
        array $rotationGroupIds,
    ): array {
        $expected = $this->absenceService->getExpectedEmployees($date, $departmentId, $rotationIds, $rotationGroupIds);
        $absent = $this->absenceService->getAbsentEmployees($date, $departmentId, $rotationIds, $rotationGroupIds);

        $absentDetails = collect();
        if ($absent->isNotEmpty()) {
            $absentDetails = DB::table('users')
                ->whereIn('users.id', $absent->toArray())
                ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                ->leftJoin('branches', 'users.branch_id', '=', 'branches.id')
                ->leftJoin('positions', 'users.position_id', '=', 'positions.id')
                ->leftJoin('grades', 'users.grade_id', '=', 'grades.id')
                ->get([
                    'users.id',
                    'users.name',
                    'users.employee_code',
                    'users.phone',
                    'users.job_title',
                    'users.department_id',
                    'users.branch_id',
                    'users.position_id',
                    'users.grade_id',
                    'departments.department_name',
                    'branches.branch_name',
                    'positions.position_name',
                    'grades.grade_name',
                ]);

            $absentAssignments = $this->rotationAssignmentRepository->getAssignmentsForDate($dateStr);

            $absentDetails = $absentDetails->map(function ($row) use ($absentAssignments) {
                $assignment = $absentAssignments->firstWhere('employee_id', $row->id);
                $row->rotation_name = $assignment?->rotation?->name;
                $row->rotation_group_name = $assignment?->rotationGroup?->name;
                $row->expected_in = $assignment?->rotationGroup?->timeSchedule?->in_time;
                $row->expected_out = $assignment?->rotationGroup?->timeSchedule?->out_time;
                $row->status = 'absent';

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
