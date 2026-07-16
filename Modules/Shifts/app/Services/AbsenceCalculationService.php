<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Holidays\Models\Holiday;
use Modules\Shifts\Models\EmployeeShiftCategory;
use Modules\Shifts\Models\ShiftException;
use Modules\Shifts\Repositories\EmployeeShiftCategoryRepository;
use Modules\Vacations\Models\UserVacationRequest;

class AbsenceCalculationService
{
    public function __construct(
        private EmployeeShiftCategoryRepository $assignmentRepository,
        private CyclicScheduleCalculator $cyclicCalculator
    ) {}

    /**
     * Get employee IDs expected to work on the given date.
     *
     * @return Collection<int, EmployeeShiftCategory>
     */
    public function getExpectedEmployees(Carbon $date): Collection
    {
        $assignments = $this->assignmentRepository->getAssignmentsForDate($date->toDateString());
        $expectedIds = collect();

        foreach ($assignments as $assignment) {
            if ($this->isEmployeeExpectedToWork($assignment->employee_id, $date)) {
                $expectedIds->push($assignment->employee_id);
            }
        }

        return $expectedIds->unique()->values();
    }

    /**
     * Get the list of absent employees for a given date.
     *
     * @return Collection<int, int>
     */
    public function getAbsentEmployees(Carbon $date, ?int $departmentId = null): Collection
    {
        $expected = $this->getExpectedEmployees($date);

        if ($expected->isEmpty()) {
            return collect();
        }

        // Filter by department if requested
        if ($departmentId !== null) {
            $expected = $expected->filter(function ($employeeId) use ($departmentId) {
                return DB::table('users')
                    ->where('id', $employeeId)
                    ->where('department_id', $departmentId)
                    ->exists();
            });
        }

        $dateStr = $date->toDateString();

        // Filter out employees who have punches in iclock_transaction
        $punchedIds = DB::table('iclock_transaction')
            ->whereIn('emp_id', $expected->toArray())
            ->whereDate('punch_time', $dateStr)
            ->distinct()
            ->pluck('emp_id');

        $absent = $expected->diff($punchedIds)->values();

        // Filter out employees with approved leave overlapping this date
        $onLeaveIds = UserVacationRequest::where('status', UserVacationRequest::STATUS_APPROVED)
            ->whereIn('user_id', $absent->toArray())
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->distinct()
            ->pluck('user_id');

        // Also bypass employees intercepted by the isolated Shift_Exceptions
        // table (manual leaves / missions / swaps) — fail-fast interceptor.
        $interceptedIds = ShiftException::active()
            ->whereIn('employee_id', $absent->toArray())
            ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
            ->where('from_date', '<=', $dateStr)
            ->where('to_date', '>=', $dateStr)
            ->distinct()
            ->pluck('employee_id');

        $absent = $absent->diff($onLeaveIds)->diff($interceptedIds)->values();

        // Filter out if it's a holiday for the employee
        if (Holiday::where('is_active', true)->whereDate('date', $dateStr)->exists()) {
            return collect();
        }

        return $absent;
    }

    /**
     * Determine absence days for a given employee in a specific month.
     *
     * @return array<int, array{date: string, status: string}>
     */
    public function getMonthlyAbsence(int $employeeId, int $month, int $year): array
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $result = [];

        $assignment = $this->assignmentRepository->getActiveAssignment($employeeId);

        if (! $assignment) {
            return [];
        }

        $category = $assignment->shiftCategory;

        $current = $startOfMonth->copy();
        while ($current->lte($endOfMonth)) {
            $dateStr = $current->toDateString();

            if ($this->isExpectedViaCategory($category, $assignment->start_date, $current)) {
                $hasPunch = DB::table('iclock_transaction')
                    ->where('emp_id', $employeeId)
                    ->whereDate('punch_time', $dateStr)
                    ->exists();

                $approvedLeave = UserVacationRequest::where('status', UserVacationRequest::STATUS_APPROVED)
                    ->where('user_id', $employeeId)
                    ->where('start_date', '<=', $dateStr)
                    ->where('end_date', '>=', $dateStr)
                    ->exists();

                $intercepted = ShiftException::active()
                    ->where('employee_id', $employeeId)
                    ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
                    ->where('from_date', '<=', $dateStr)
                    ->where('to_date', '>=', $dateStr)
                    ->exists();

                $status = 'present';
                if ($approvedLeave || $intercepted) {
                    $status = 'on_leave';
                } elseif (! $hasPunch) {
                    $status = 'absent';
                }

                $result[] = [
                    'date' => $dateStr,
                    'status' => $status,
                ];
            }

            $current->addDay();
        }

        return $result;
    }

    /**
     * Determine whether a specific employee is expected to work on the given date.
     */
    public function isEmployeeExpectedToWork(int $employeeId, Carbon $date): bool
    {
        $assignment = $this->assignmentRepository->getActiveAssignment($employeeId);

        if (! $assignment) {
            return false;
        }

        $category = $assignment->shiftCategory;

        return $this->isExpectedViaCategory($category, $assignment->start_date, $date);
    }

    /**
     * Check if an employee with the given category is expected to work.
     */
    private function isExpectedViaCategory($category, $assignmentStart, Carbon $date): bool
    {
        $type = $category->type;

        if ($type === 'cyclic') {
            $startDate = Carbon::parse($assignmentStart)->startOfDay();

            return $this->cyclicCalculator->isWorkDay(
                $date,
                $startDate,
                (int) ($category->work_days ?? 0),
                (int) ($category->rest_days ?? 0)
            );
        }

        if ($type === 'weekly') {
            $workDaysJson = $category->work_days_json;
            if (! is_array($workDaysJson)) {
                return true;
            }
            $dayOfWeek = $date->dayOfWeek;

            return in_array($dayOfWeek, $workDaysJson);
        }

        if ($type === 'hours') {
            return true;
        }

        return false;
    }
}
