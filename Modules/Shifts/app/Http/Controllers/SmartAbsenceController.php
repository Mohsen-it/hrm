<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Shifts\Exports\SmartAbsenceDailyExport;
use Modules\Shifts\Exports\SmartAbsenceMonthlyExport;
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

        $rotations = $this->buildRotationOptions();
        $departments = $this->buildDepartmentOptions();

        $totalExpected = $report['expected']->count();
        $totalAbsent = $report['absent']->count();
        $attendanceRate = $totalExpected > 0
            ? (int) round((($totalExpected - $totalAbsent) / $totalExpected) * 100)
            : 100;

        $page = $request->integer('page', 1);
        $perPage = $request->input('per_page', 20);

        if ($perPage === 'all' || $perPage === -1) {
            $perPage = $report['absentDetails']->count();
            $page = 1;
        } else {
            $perPage = (int) $perPage;
        }

        $absentPaginator = new LengthAwarePaginator(
            $report['absentDetails']->forPage($page, $perPage),
            $report['absentDetails']->count(),
            $perPage,
            $page,
            ['path' => route('smart-absence.daily')]
        );

        return Inertia::render('Shifts/Absence/SmartAbsenceReport', [
            'dailyData' => [
                'date' => $dateStr,
                'expected' => $report['expected']->toArray(),
                'absent' => $absentPaginator->toArray(),
                'total_expected' => $totalExpected,
                'total_absent' => $totalAbsent,
                'attendance_rate' => $attendanceRate,
            ],
            'rotations' => $rotations,
            'departments' => $departments,
            'monthlyData' => [],
            'monthlyReportData' => [],
            'filters' => [
                'department_id' => $departmentId,
                'rotation_ids' => $rotationIds,
                'rotation_group_ids' => $rotationGroupIds,
                'date' => $dateStr,
            ],
        ]);
    }

    /**
     * Monthly / date-range smart absence report, aggregated like the daily one.
     *
     * Every employee expected to work at least one day inside the selected
     * range appears with their expected / present / absent day counts, using
     * the exact same rotation + attendance calculation as the daily report.
     */
    public function monthlyReport(Request $request): Response
    {
        $this->authorize('view-attendance-by-schedule');

        [$from, $to] = $this->parseRangeDates($request);

        $departmentId = $request->input('department_id') ? (int) $request->input('department_id') : null;
        $rotationIds = $this->parseIdList($request->input('rotation_ids', $request->input('rotation_id')));
        $rotationGroupIds = $this->parseIdList($request->input('rotation_group_ids', $request->input('rotation_group_id')));

        $report = $this->absenceService->getMonthlyAbsenceReport($from, $to, $departmentId, $rotationIds, $rotationGroupIds);

        $absentDetails = $this->buildMonthlyAbsentDetails($report);

        $totalExpectedDays = $report['total_expected_days'];
        $totalAbsentDays = $report['total_absent_days'];
        $totalPresentDays = $report['total_present_days'];
        $attendanceRate = $totalExpectedDays > 0
            ? (int) round(($totalPresentDays / $totalExpectedDays) * 100)
            : 100;

        $page = $request->integer('page', 1);
        $perPage = $request->input('per_page', 20);

        if ($perPage === 'all' || $perPage === -1) {
            $perPage = $absentDetails->count();
            $page = 1;
        } else {
            $perPage = (int) $perPage;
        }

        $paginator = new LengthAwarePaginator(
            $absentDetails->forPage($page, $perPage),
            $absentDetails->count(),
            $perPage,
            $page,
            ['path' => route('smart-absence.monthly.report')]
        );

        return Inertia::render('Shifts/Absence/SmartAbsenceReport', [
            'dailyData' => [],
            'monthlyData' => [],
            'monthlyReportData' => [
                'employees' => $paginator->toArray(),
                'total_expected_days' => $totalExpectedDays,
                'total_absent_days' => $totalAbsentDays,
                'total_present_days' => $totalPresentDays,
                'attendance_rate' => $attendanceRate,
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
            ],
            'rotations' => $this->buildRotationOptions(),
            'departments' => $this->buildDepartmentOptions(),
            'filters' => [
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'department_id' => $departmentId,
                'rotation_ids' => $rotationIds,
                'rotation_group_ids' => $rotationGroupIds,
                'month' => (int) $from->month,
                'year' => (int) $from->year,
            ],
        ]);
    }

    /**
     * Export the monthly smart-absence report as a fully-formatted .xlsx file
     * with Arabic / RTL support.
     */
    public function exportMonthly(Request $request): HttpResponse
    {
        $this->authorize('view-attendance-by-schedule');

        [$from, $to] = $this->parseRangeDates($request);

        $departmentId = $request->input('department_id') ? (int) $request->input('department_id') : null;
        $rotationIds = $this->parseIdList($request->input('rotation_ids', $request->input('rotation_id')));
        $rotationGroupIds = $this->parseIdList($request->input('rotation_group_ids', $request->input('rotation_group_id')));

        $report = $this->absenceService->getMonthlyAbsenceReport($from, $to, $departmentId, $rotationIds, $rotationGroupIds);

        $export = new SmartAbsenceMonthlyExport(
            fromDate: $from->toDateString(),
            toDate: $to->toDateString(),
            totalExpectedDays: $report['total_expected_days'],
            totalAbsentDays: $report['total_absent_days'],
            employees: $this->buildMonthlyAbsentDetails($report),
            statusLabel: __('shifts.absent_short', [], null) ?: 'غياب',
        );

        $fileName = "smart-absence-{$from->toDateString()}_{$to->toDateString()}.xlsx";
        $content = $export->toBinary();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"; filename*=UTF-8''".rawurlencode($fileName),
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Parse the inclusive from/to date range (defaults to the current month).
     *
     * @return array{Carbon, Carbon}
     */
    private function parseRangeDates(Request $request): array
    {
        $from = $request->filled('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to_date')
            ? Carbon::parse($request->input('to_date'))->startOfDay()
            : now()->endOfMonth();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * Enrich the monthly stats with employee details and keep only the
     * employees with at least one non-attended expected day inside the range
     * (absent, on vacation or on a shift exception - the same scenario as the
     * daily report, plus covered days the user wants to see), most impactful
     * first.
     *
     * @param  array{employees: Collection, total_expected_days: int, total_absent_days: int, total_present_days: int}  $report
     */
    private function buildMonthlyAbsentDetails(array $report): Collection
    {
        $statsById = $report['employees']->keyBy('employee_id');

        if ($statsById->isEmpty()) {
            return collect();
        }

        $details = DB::table('users')
            ->whereIn('users.id', $statsById->keys()->toArray())
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

        return $details
            ->map(function ($row) use ($statsById) {
                $stat = $statsById->get($row->id, []);
                $expected = (int) ($stat['expected'] ?? 0);
                $present = (int) ($stat['present'] ?? 0);
                $absentDates = $stat['absent_dates'] ?? [];
                $absent = count($absentDates);
                $dayDetails = $stat['day_details'] ?? [];

                $row->expected_days = $expected;
                $row->present_days = $present;
                $row->vacation_days = collect($dayDetails)->where('status', 'vacation')->count();
                $row->exception_days = collect($dayDetails)->where('status', 'exception')->count();
                $row->holiday_days = collect($dayDetails)->where('status', 'holiday')->count();
                $row->absent_days = $absent;
                $row->day_details = array_values($dayDetails);
                $row->absent_dates = $absentDates;
                $row->attendance_rate = $expected > 0 ? (int) round(($present / $expected) * 100) : 100;
                $row->rotation_name = $stat['rotation_name'] ?? null;
                $row->rotation_group_name = $stat['rotation_group_name'] ?? null;
                $row->expected_in = $stat['expected_in'] ?? null;
                $row->expected_out = $stat['expected_out'] ?? null;
                $row->status = 'absent';

                return $row;
            })
            ->filter(fn ($row) => $row->absent_days > 0 || $row->vacation_days > 0 || $row->exception_days > 0)
            ->sortBy('name')
            ->sortByDesc(fn ($row) => $row->absent_days + $row->vacation_days + $row->exception_days)
            ->values();
    }

    /**
     * Rotation + group options used by the report filter bars.
     *
     * @return array<int, array{id: int, name: string, groups: array<int, array{id: int, name: string}>}>
     */
    private function buildRotationOptions(): array
    {
        return $this->rotationRepository->getAllList()->map(fn ($rotation) => [
            'id' => $rotation->id,
            'name' => $rotation->name,
            'groups' => $rotation->groups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
            ])->toArray(),
        ])->toArray();
    }

    /**
     * Active department options used by the report filter bars.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function buildDepartmentOptions(): array
    {
        return DB::table('departments')
            ->where('status', 1)
            ->orderBy('department_name')
            ->get(['id', 'department_name'])
            ->map(fn ($dept) => ['id' => (int) $dept->id, 'name' => $dept->department_name])
            ->all();
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

            $absentAssignments = $this->rotationAssignmentRepository->getLatestActiveAssignments();

            $absentDetails = $absentDetails->map(function ($row) use ($absentAssignments) {
                $assignment = $absentAssignments->firstWhere('employee_id', $row->id);
                $row->rotation_name = $assignment?->rotation?->name;
                $row->rotation_group_name = $assignment?->rotationGroup?->name;
                $row->expected_in = $assignment?->rotation?->timeSchedule?->in_time;
                $row->expected_out = $assignment?->rotation?->timeSchedule?->out_time;
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
            'monthlyReportData' => [],
            'rotations' => $this->buildRotationOptions(),
            'departments' => $this->buildDepartmentOptions(),
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
