<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Modules\Shifts\Repositories\RotationAssignmentRepository;
use Modules\Shifts\Repositories\ShiftExceptionRepository;
use Modules\Vacations\Models\UserVacationRequest;

/**
 * ScheduleResolverService — the central Dynamic Shift Engine resolver.
 *
 * Given a single employee and a target date it returns a clean, standardised
 * contract payload. Execution follows a STRICT FAIL-FAST order:
 *
 *   1. Rotation Resolution — fetch the active rotation assignment for the employee.
 *   2. Leave Interceptor   — query Shift_Exceptions (and approved vacations).
 *                           If an exception intercepts, short-circuit and
 *                           return `{ is_work_day: false, status: 'leave_excused' }`
 *                           so the employee is NEVER flagged absent.
 *   3. Engine Math         — delegate to RotationEngine for work/rest calculation.
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
        private ShiftExceptionRepository $exceptionRepository,
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
     *     rotation_id: ?int,
     *     rotation_group_id: ?int,
     *     exception_id: ?int,
     *     source: string
     * }
     */
    public function resolve(int $employeeId, Carbon|string $targetDate): array
    {
        $date = Carbon::parse($targetDate)->startOfDay();
        $dateStr = $date->toDateString();

        // --- Step 1: Rotation Resolution ---------------------------------
        $rotationAssignment = $this->rotationAssignmentRepository->getAssignmentForDate($employeeId, $dateStr);

        if (! $rotationAssignment) {
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

        // --- Step 3: Engine Math (Rotation) -----------------------------
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
        ?int $rotationId = null,
        ?int $rotationGroupId = null,
        ?int $exceptionId = null,
        string $source = 'rotation',
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
            'rotation_id' => $rotationId,
            'rotation_group_id' => $rotationGroupId,
            'exception_id' => $exceptionId,
            'source' => $source,
        ];
    }
}
