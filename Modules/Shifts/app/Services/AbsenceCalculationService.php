<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Holidays\Models\Holiday;
use Modules\Shifts\Models\ShiftException;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Vacations\Models\UserVacationRequest;

class AbsenceCalculationService
{
    public function __construct(
        private RotationAssignmentRepository $rotationAssignmentRepository,
        private RotationEngine $rotationEngine,
    ) {}

    /**
     * Get employee IDs expected to work on the given date.
     *
     * @return Collection<int, int>
     */
    public function getExpectedEmployees(
        Carbon $date,
        ?int $departmentId = null,
        ?int $rotationId = null,
        ?int $rotationGroupId = null,
    ): Collection {
        $dateStr = $date->toDateString();

        $rotationAssignments = $this->rotationAssignmentRepository->getAssignmentsForDate($dateStr);
        $expectedIds = collect();

        foreach ($rotationAssignments as $rotationAssignment) {
            $rotation = $rotationAssignment->rotation;
            $group = $rotationAssignment->rotationGroup;

            if ($rotationId !== null && $rotation->id !== $rotationId) {
                continue;
            }

            if ($rotationGroupId !== null && $group->id !== $rotationGroupId) {
                continue;
            }

            if ($this->rotationEngine->isWorkDay($rotation, $group, $date)) {
                $expectedIds->push($rotationAssignment->employee_id);
            }
        }

        $expectedIds = $expectedIds->unique()->values();

        if ($expectedIds->isEmpty()) {
            return $expectedIds;
        }

        $query = DB::table('users')
            ->whereIn('id', $expectedIds->toArray())
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('termination_date')
                    ->orWhere('termination_date', '>=', $dateStr);
            });

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        return $query->pluck('id');
    }

    /**
     * Get the list of absent employees for a given date.
     *
     * @return Collection<int, int>
     */
    public function getAbsentEmployees(
        Carbon $date,
        ?int $departmentId = null,
        ?int $rotationId = null,
        ?int $rotationGroupId = null,
    ): Collection {
        $expected = $this->getExpectedEmployees($date, $departmentId, $rotationId, $rotationGroupId);

        if ($expected->isEmpty()) {
            return collect();
        }

        $dateStr = $date->toDateString();

        $punchedIds = AttendanceSession::onDate($dateStr)
            ->whereIn('user_id', $expected->toArray())
            ->distinct()
            ->pluck('user_id');

        $absent = $expected->diff($punchedIds)->values();

        $onLeaveIds = UserVacationRequest::where('status', UserVacationRequest::STATUS_APPROVED)
            ->whereIn('user_id', $absent->toArray())
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->distinct()
            ->pluck('user_id');

        $interceptedIds = ShiftException::active()
            ->whereIn('employee_id', $absent->toArray())
            ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
            ->where('from_date', '<=', $dateStr)
            ->where('to_date', '>=', $dateStr)
            ->distinct()
            ->pluck('employee_id');

        $absent = $absent->diff($onLeaveIds)->diff($interceptedIds)->values();

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

        $employee = DB::table('users')
            ->where('id', $employeeId)
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->first();

        if (! $employee) {
            return [];
        }

        $rotationAssignment = $this->rotationAssignmentRepository->getActiveAssignment($employeeId);

        if (! $rotationAssignment) {
            return [];
        }

        $rotation = $rotationAssignment->rotation;
        $group = $rotationAssignment->rotationGroup;

        $current = $startOfMonth->copy();
        while ($current->lte($endOfMonth)) {
            $dateStr = $current->toDateString();
            $isExpected = $this->rotationEngine->isWorkDay($rotation, $group, $current);

            if ($isExpected) {
                $hasPunch = AttendanceSession::onDate($dateStr)
                    ->where('user_id', $employeeId)
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
        $employee = DB::table('users')
            ->where('id', $employeeId)
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->first();

        if (! $employee) {
            return false;
        }

        $rotationAssignment = $this->rotationAssignmentRepository->getActiveAssignment($employeeId);

        if ($rotationAssignment) {
            $rotation = $rotationAssignment->rotation;
            $group = $rotationAssignment->rotationGroup;

            return $this->rotationEngine->isWorkDay($rotation, $group, $date);
        }

        return false;
    }
}
