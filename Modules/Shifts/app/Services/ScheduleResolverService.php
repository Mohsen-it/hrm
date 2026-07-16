<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Modules\Shifts\Models\EmployeeShiftCategory;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\ShiftCategory;
use Modules\Shifts\Repositories\EmployeeShiftCategoryRepository;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Repositories\ShiftExceptionRepository;
use Modules\Vacations\Models\UserVacationRequest;

/**
 * ScheduleResolverService — the isolated Dynamic Shift Engine resolver.
 *
 * Given a single employee and a target date it returns a clean, standardised
 * contract payload. Execution follows a STRICT FAIL-FAST order:
 *
 *   1. Scope Resolution   — fetch the active group assignment from history.
 *   2. Leave Interceptor   — query Shift_Exceptions (and approved vacations).
 *                           If an exception intercepts, short-circuit and
 *                           return `{ is_work_day: false, status: 'leave_excused' }`
 *                           so the employee is NEVER flagged absent.
 *   3. Engine Math         — otherwise resolve the dynamic Cycle_Length and
 *                           apply the mapping equation against the group anchor.
 *
 * No loops over date ranges are used — all math is closed-form / DB-level.
 */
class ScheduleResolverService
{
    /**
     * Standardised status constants returned by the resolver contract.
     */
    public const STATUS_WORK = 'work';

    public const STATUS_REST = 'rest';

    public const STATUS_LEAVE_EXCUSED = 'leave_excused';

    public const STATUS_SWAP = 'swap';

    public const STATUS_UNASSIGNED = 'unassigned';

    public function __construct(
        private EmployeeShiftCategoryRepository $assignmentRepository,
        private ShiftExceptionRepository $exceptionRepository,
        private CyclicScheduleCalculator $cyclicCalculator,
        private RotationAssignmentRepository $rotationAssignmentRepository,
        private RotationEngine $rotationEngine,
    ) {}

    /**
     * Resolve one employee's schedule status for a target date.
     *
     * @return array{
     *     employee_id: int,
     *     target_date: string,
     *     is_work_day: bool,
     *     status: string,
     *     expected_check_in: ?string,
     *     expected_check_out: ?string,
     *     day_index: ?int,
     *     cycle_length: ?int,
     *     shift_category_id: ?int,
     *     exception_id: ?int,
     *     source: string
     * }
     */
    public function resolve(int $employeeId, Carbon|string $targetDate): array
    {
        $date = Carbon::parse($targetDate)->startOfDay();
        $dateStr = $date->toDateString();

        // --- Step 1: Scope Resolution ------------------------------------
        $assignment = $this->assignmentRepository->getAssignmentForDate($employeeId, $dateStr);

        if (! $assignment) {
            // --- Step 1b: Check rotation assignment ---------------------
            $rotationResult = $this->resolveRotation($employeeId, $dateStr);

            if ($rotationResult !== null) {
                return $rotationResult;
            }

            return $this->contract(
                employeeId: $employeeId,
                date: $dateStr,
                isWorkDay: false,
                status: self::STATUS_UNASSIGNED,
                source: 'unassigned'
            );
        }

        // --- Step 2: Leave / Exception Interceptor (fail-fast) ----------
        $exception = $this->exceptionRepository->findIntercepting($employeeId, $dateStr);

        if ($exception && $exception->intercepts()) {
            return $this->contract(
                employeeId: $employeeId,
                date: $dateStr,
                isWorkDay: false,
                status: $exception->exception_type === 'swap' ? self::STATUS_SWAP : self::STATUS_LEAVE_EXCUSED,
                exceptionId: $exception->id,
                source: 'exception'
            );
        }

        // Fallback interceptor: approved vacations (preserves prior behaviour,
        // keeps the resolver self-contained even before the mirror listener runs).
        $hasVacation = UserVacationRequest::where('user_id', $employeeId)
            ->where('status', UserVacationRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->exists();

        if ($hasVacation) {
            return $this->contract(
                employeeId: $employeeId,
                date: $dateStr,
                isWorkDay: false,
                status: self::STATUS_LEAVE_EXCUSED,
                source: 'vacation'
            );
        }

        // --- Step 3: Fallback to Engine Math ----------------------------
        return $this->resolveEngine($assignment, $date, $dateStr);
    }

    /**
     * Apply the dynamic engine math once scope + interceptor are clear.
     */
    private function resolveEngine(EmployeeShiftCategory $assignment, Carbon $date, string $dateStr): array
    {
        $category = $assignment->shiftCategory;
        $type = $category->type;

        $expectedIn = $this->expectedCheckIn($assignment, $category);
        $expectedOut = $this->expectedCheckOut($assignment, $category);

        if ($type === 'cyclic') {
            $anchor = $category->cycleAnchor()
                ?? Carbon::parse($assignment->start_date)->startOfDay();
            $cycleLength = $category->cycleLength();

            if ($cycleLength <= 0) {
                return $this->contract(
                    employeeId: $assignment->employee_id,
                    date: $dateStr,
                    isWorkDay: false,
                    status: self::STATUS_REST,
                    expectedIn: $expectedIn,
                    expectedOut: $expectedOut,
                    cycleLength: $cycleLength,
                    categoryId: $category->id,
                    source: 'assignment'
                );
            }

            $dayIndex = $this->cyclicCalculator->dayIndex($date, $anchor, $cycleLength);
            $workDays = (int) ($category->work_days ?? 0);
            $isWorkDay = $dayIndex !== null && $dayIndex <= $workDays;

            return $this->contract(
                employeeId: $assignment->employee_id,
                date: $dateStr,
                isWorkDay: $isWorkDay,
                status: $isWorkDay ? self::STATUS_WORK : self::STATUS_REST,
                expectedIn: $expectedIn,
                expectedOut: $expectedOut,
                dayIndex: $dayIndex,
                cycleLength: $cycleLength,
                categoryId: $category->id,
                source: 'assignment'
            );
        }

        if ($type === 'weekly') {
            $workDaysJson = $category->work_days_json;
            $isWorkDay = is_array($workDaysJson) && in_array($date->dayOfWeek, $workDaysJson, true);

            return $this->contract(
                employeeId: $assignment->employee_id,
                date: $dateStr,
                isWorkDay: $isWorkDay,
                status: $isWorkDay ? self::STATUS_WORK : self::STATUS_REST,
                expectedIn: $expectedIn,
                expectedOut: $expectedOut,
                categoryId: $category->id,
                source: 'assignment'
            );
        }

        // 'hours' type — every day is a potential work day.
        return $this->contract(
            employeeId: $assignment->employee_id,
            date: $dateStr,
            isWorkDay: true,
            status: self::STATUS_WORK,
            expectedIn: $expectedIn,
            expectedOut: $expectedOut,
            categoryId: $category->id,
            source: 'assignment'
        );
    }

    /**
     * Build the standardised resolver contract payload.
     */
    private function contract(
        int $employeeId,
        string $date,
        bool $isWorkDay,
        string $status,
        ?string $expectedIn = null,
        ?string $expectedOut = null,
        ?int $dayIndex = null,
        ?int $cycleLength = null,
        ?int $categoryId = null,
        ?int $exceptionId = null,
        string $source = 'assignment',
    ): array {
        return [
            'employee_id' => $employeeId,
            'target_date' => $date,
            'is_work_day' => $isWorkDay,
            'status' => $status,
            'expected_check_in' => $expectedIn,
            'expected_check_out' => $expectedOut,
            'day_index' => $dayIndex,
            'cycle_length' => $cycleLength,
            'shift_category_id' => $categoryId,
            'exception_id' => $exceptionId,
            'source' => $source,
        ];
    }

    private function expectedCheckIn(EmployeeShiftCategory $assignment, ShiftCategory $category): ?string
    {
        $snapshot = $assignment->snapshot_data;
        $fromSnapshot = $snapshot['time_schedule']['in_time'] ?? null;
        if ($fromSnapshot) {
            return substr((string) $fromSnapshot, 0, 5);
        }

        return optional($category->timeSchedule?->in_time)->format('H:i');
    }

    private function expectedCheckOut(EmployeeShiftCategory $assignment, ShiftCategory $category): ?string
    {
        $snapshot = $assignment->snapshot_data;
        $fromSnapshot = $snapshot['time_schedule']['out_time'] ?? null;
        if ($fromSnapshot) {
            return substr((string) $fromSnapshot, 0, 5);
        }

        return optional($category->timeSchedule?->out_time)->format('H:i');
    }

    /**
     * Resolve employee schedule from rotation assignment.
     *
     * Checks for an active rotation assignment, verifies leave/exception
     * interceptors, then delegates to the RotationEngine.
     */
    private function resolveRotation(int $employeeId, string $dateStr): ?array
    {
        $rotationAssignment = $this->rotationAssignmentRepository->getAssignmentForDate($employeeId, $dateStr);

        if (! $rotationAssignment) {
            return null;
        }

        // Leave / Exception Interceptor (same fail-fast as shift category)
        $exception = $this->exceptionRepository->findIntercepting($employeeId, $dateStr);

        if ($exception && $exception->intercepts()) {
            return $this->contract(
                employeeId: $employeeId,
                date: $dateStr,
                isWorkDay: false,
                status: $exception->exception_type === 'swap' ? self::STATUS_SWAP : self::STATUS_LEAVE_EXCUSED,
                exceptionId: $exception->id,
                source: 'rotation_exception'
            );
        }

        $hasVacation = UserVacationRequest::where('user_id', $employeeId)
            ->where('status', UserVacationRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->exists();

        if ($hasVacation) {
            return $this->contract(
                employeeId: $employeeId,
                date: $dateStr,
                isWorkDay: false,
                status: self::STATUS_LEAVE_EXCUSED,
                source: 'rotation_vacation'
            );
        }

        $rotation = $rotationAssignment->rotation;
        $group = $rotationAssignment->rotationGroup;
        $times = $this->rotationEngine->resolveTimes($rotationAssignment);

        return $this->rotationEngine->resolve(
            employeeId: $employeeId,
            rotation: $rotation,
            group: $group,
            targetDate: $dateStr,
            expectedCheckIn: $times['check_in'],
            expectedCheckOut: $times['check_out'],
        );
    }
}
