<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Holidays\Models\Holiday;
use Modules\Shifts\Services\AbsenceCalculationService;
use Modules\Shifts\Services\CyclicScheduleCalculator;
use Modules\Shifts\Services\ShiftCategoryAssignmentService;
use Modules\Vacations\Models\UserVacationRequest;

class ScheduleCalendarController extends Controller
{
    public function __construct(
        private ShiftCategoryAssignmentService $assignmentService,
        private CyclicScheduleCalculator $cyclicCalculator
    ) {}

    /**
     * Show the 30-day calendar for a specific employee.
     */
    public function employee(int $id, ?string $month = null, ?string $year = null): Response
    {
        $this->authorize('view-shift-categories');

        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $assignment = $this->assignmentService->getActiveAssignment($id);

        $calendar = $this->buildCalendar($assignment, (int) $month, (int) $year);

        return Inertia::render('Shifts/Calendar/EmployeeCalendar', [
            'calendar' => $calendar,
            'employee' => ['id' => $id],
            'month' => (int) $month,
            'year' => (int) $year,
        ]);
    }

    /**
     * List employee day statuses for a department on a given date.
     */
    public function department(int $departmentId, ?string $date = null): Response
    {
        $this->authorize('view-shift-categories');

        $date = $date ? Carbon::parse($date) : now();
        $dateStr = $date->toDateString();

        $employees = DB::table('users')
            ->where('department_id', $departmentId)
            ->where('is_active_employee', true)
            ->get(['id', 'name', 'employee_code']);

        $statuses = [];
        foreach ($employees as $emp) {
            $statuses[] = [
                'id' => $emp->id,
                'name' => $emp->name,
                'employee_code' => $emp->employee_code,
                'is_expected' => app(AbsenceCalculationService::class)->isEmployeeExpectedToWork($emp->id, $date),
                'has_punch' => DB::table('iclock_transaction')
                    ->where('emp_id', $emp->id)
                    ->whereDate('punch_time', $dateStr)
                    ->exists(),
            ];
        }

        return Inertia::render('Shifts/Calendar/DepartmentCalendar', [
            'departmentId' => $departmentId,
            'date' => $dateStr,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Show team calendar filtered by manager's team.
     */
    public function teamSchedule(Request $request): Response
    {
        $user = auth()->user();
        $teamIds = DB::table('users')
            ->where('manager_id', $user->id)
            ->pluck('id')
            ->toArray();

        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        return Inertia::render('Shifts/Calendar/TeamCalendar', [
            'teamIds' => $teamIds,
            'month' => (int) $month,
            'year' => (int) $year,
        ]);
    }

    /**
     * Show logged-in user's calendar.
     */
    public function myCalendar(Request $request): Response
    {
        $userId = auth()->id();
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $assignment = $this->assignmentService->getActiveAssignment($userId);
        $calendar = $this->buildCalendar($assignment, (int) $month, (int) $year);

        return Inertia::render('Shifts/Calendar/MyCalendar', [
            'calendar' => $calendar,
            'month' => (int) $month,
            'year' => (int) $year,
        ]);
    }

    /**
     * Build a calendar array for a given assignment, month, and year.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCalendar($assignment, int $month, int $year): array
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $calendar = [];

        if (! $assignment) {
            $current = $startOfMonth->copy();
            $arabicDays = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
            while ($current->lte($endOfMonth)) {
                $calendar[] = [
                    'date' => $current->toDateString(),
                    'day_name' => $arabicDays[$current->dayOfWeek],
                    'day_of_week' => $current->dayOfWeek,
                    'status' => 'rest',
                    'is_work_day' => false,
                    'in_time' => null,
                    'out_time' => null,
                ];
                $current->addDay();
            }

            return $calendar;
        }

        $category = $assignment->shiftCategory;
        $startDate = Carbon::parse($assignment->start_date)->startOfDay();
        $workDays = (int) ($category->work_days ?? 0);
        $restDays = (int) ($category->rest_days ?? 0);
        $arabicDays = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        $userId = $assignment->employee_id;

        $snapshot = $assignment->snapshot_data;
        $inTime = $snapshot['time_schedule']['in_time'] ?? null;
        $outTime = $snapshot['time_schedule']['out_time'] ?? null;

        // For multi-day: identify work blocks
        $workBlocks = [];
        if ($category->type === 'cyclic') {
            $workBlocks = $this->cyclicCalculator->getWorkBlocks($startDate, $workDays, $restDays, $startOfMonth, $endOfMonth);
        }

        $current = $startOfMonth->copy();
        while ($current->lte($endOfMonth)) {
            $dateStr = $current->toDateString();
            $isWorkDay = false;

            if ($category->type === 'cyclic') {
                $isWorkDay = $this->cyclicCalculator->isWorkDay($current, $startDate, $workDays, $restDays);

                // Multi-day: if not a work day but falls between work block start and end, auto-present
                if (! $isWorkDay) {
                    foreach ($workBlocks as $block) {
                        $blockStart = Carbon::parse($block['start_date']);
                        $blockEnd = Carbon::parse($block['end_date']);
                        if ($current->between($blockStart, $blockEnd)) {
                            $isWorkDay = true;
                            break;
                        }
                    }
                }
            } elseif ($category->type === 'weekly') {
                $workDaysJson = $category->work_days_json;
                $isWorkDay = is_array($workDaysJson) && in_array($current->dayOfWeek, $workDaysJson);
            } elseif ($category->type === 'hours') {
                $isWorkDay = true;
            }

            $hasPunch = DB::table('iclock_transaction')
                ->where('emp_id', $userId)
                ->whereDate('punch_time', $dateStr)
                ->exists();

            $approvedLeave = UserVacationRequest::where('status', 'approved')
                ->where('user_id', $userId)
                ->where('start_date', '<=', $dateStr)
                ->where('end_date', '>=', $dateStr)
                ->exists();

            $isHoliday = Holiday::where('is_active', true)->whereDate('date', $dateStr)->exists();

            $status = 'rest';
            if ($isHoliday) {
                $status = 'holiday';
            } elseif ($isWorkDay && $hasPunch) {
                $status = 'present';
            } elseif ($isWorkDay && $approvedLeave) {
                $status = 'on_leave';
            } elseif ($isWorkDay) {
                $status = 'absent';
            }

            $calendar[] = [
                'date' => $dateStr,
                'day_name' => $arabicDays[$current->dayOfWeek],
                'day_of_week' => $current->dayOfWeek,
                'status' => $status,
                'is_work_day' => $isWorkDay,
                'in_time' => $inTime,
                'out_time' => $outTime,
            ];

            $current->addDay();
        }

        return $calendar;
    }
}
