<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\DailyAttendanceSummary;
use Modules\Shifts\Repositories\EmployeeShiftCategoryRepository;

/**
 * BiometricShiftSyncService — Step 3 conflict-free sync.
 *
 * Consumes the ScheduleResolver contract for every scoped employee on a date
 * and derives the FINAL day status from the biometric device logs:
 *
 *   - status == 'leave_excused' | 'swap'
 *         -> final status "excused", ZERO delay / ZERO absence flags.
 *   - is_work_day == true and no punch on the device
 *         -> final status "absent".
 *
 * Edge cases handled cleanly:
 *   - Overnight shifts (is_multi_day / out < in) — the overnight check-in on
 *     the PREVIOUS day is recognised so the employee is not falsely "absent".
 *   - Timezone drift — dates are matched with `whereDate` on the device's
 *     stored `punch_time`, avoiding per-row PHP timezone conversion.
 *   - Duplicate rows — daily summaries use a unique (user_id, summary_date)
 *     key, so writes are idempotent via `updateOrInsert`.
 *
 * The service is ADDITIVE: it only forces "excused" rows (the explicit
 * requirement) and otherwise only *reports* absences, leaving the Attendance
 * module's own auto-calculation as the owner of present/late rows.
 */
class BiometricShiftSyncService
{
    public function __construct(
        private ScheduleResolverService $resolver,
        private EmployeeShiftCategoryRepository $assignmentRepository,
    ) {}

    /**
     * Resolve the final status for every scoped employee on a date (read-only).
     *
     * @return array<int, array{employee_id: int, status: string, is_work_day: bool, has_punch: bool, resolver: array}>
     */
    public function resolveDate(Carbon|string $targetDate): array
    {
        $date = Carbon::parse($targetDate)->startOfDay();
        $dateStr = $date->toDateString();

        $assignmentIds = $this->assignmentRepository->getAssignmentsForDate($dateStr)
            ->pluck('employee_id')
            ->unique()
            ->values()
            ->all();

        $out = [];
        foreach ($assignmentIds as $employeeId) {
            $resolved = $this->resolver->resolve($employeeId, $date);
            $hasPunch = $this->hasPunch($employeeId, $date, $resolved);

            $finalStatus = match ($resolved['status']) {
                ScheduleResolverService::STATUS_LEAVE_EXCUSED,
                ScheduleResolverService::STATUS_SWAP => 'excused',
                ScheduleResolverService::STATUS_WORK => $hasPunch ? 'present' : 'absent',
                default => 'rest',
            };

            $out[] = [
                'employee_id' => $employeeId,
                'status' => $finalStatus,
                'is_work_day' => $resolved['is_work_day'],
                'has_punch' => $hasPunch,
                'resolver' => $resolved,
            ];
        }

        return $out;
    }

    /**
     * Sync a date: write "excused" summary rows for intercepted employees and
     * return the full resolved matrix. Present/late rows are left to the
     * Attendance module's auto-calculation to avoid double ownership.
     */
    public function syncDate(Carbon|string $targetDate, bool $writeExcused = true): array
    {
        $date = Carbon::parse($targetDate)->startOfDay();
        $dateStr = $date->toDateString();

        $matrix = $this->resolveDate($date);

        if ($writeExcused) {
            foreach ($matrix as $row) {
                if ($row['status'] === 'excused') {
                    $this->upsertExcused($row['employee_id'], $dateStr, $row['resolver']);
                }
            }
        }

        return $matrix;
    }

    /**
     * Determine whether the employee produced a biometric punch for the day,
     * recognising overnight shifts that check in on the previous calendar day.
     */
    private function hasPunch(int $employeeId, Carbon $date, array $resolved): bool
    {
        $dateStr = $date->toDateString();

        $sameDay = DB::table('iclock_transaction')
            ->where('emp_id', $employeeId)
            ->whereDate('punch_time', $dateStr)
            ->exists();

        if ($sameDay) {
            return true;
        }

        // Overnight shift: out_time earlier than in_time means the cycle
        // crosses midnight. Accept a previous-day check-in at/after in_time.
        $in = $resolved['expected_check_in'];
        $out = $resolved['expected_check_out'];

        if ($in && $out && $out < $in) {
            $prev = $date->copy()->subDay()->toDateString();

            return DB::table('iclock_transaction')
                ->where('emp_id', $employeeId)
                ->whereDate('punch_time', $prev)
                ->whereTime('punch_time', '>=', $in.':00')
                ->exists();
        }

        return false;
    }

    /**
     * Idempotently write an "excused" daily summary row — bypassing absence.
     */
    private function upsertExcused(int $employeeId, string $dateStr, array $resolved): void
    {
        DailyAttendanceSummary::updateOrInsert(
            ['user_id' => $employeeId, 'summary_date' => $dateStr],
            [
                'status' => 'excused',
                'expected_check_in' => null,
                'expected_check_out' => null,
                'first_check_in_at' => null,
                'last_check_out_at' => null,
                'notes' => 'Auto-excused by dynamic shift engine: '.($resolved['exception_id'] ? 'exception #'.$resolved['exception_id'] : $resolved['source']),
                'calculated_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
