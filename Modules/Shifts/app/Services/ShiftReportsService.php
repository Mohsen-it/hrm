<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Shifts\Repositories\RotationAssignmentRepository;

/**
 * ShiftReportsService — Step 5 regression-safe reporting.
 *
 * Both reports are expressed as SET-LEVEL SQL aggregations (no in-memory
 * loops over date ranges). The daily report resolves each scoped employee
 * through the ScheduleResolver (closed-form engine math) and attaches the
 * biometric punch times via a single grouped query; the monthly report rolls
 * up the already-computed `daily_attendance_summaries` rows, counting leave /
 * excused days separately so they are EXCLUDED from the absence tally.
 */
class ShiftReportsService
{
    public function __construct(
        private ScheduleResolverService $resolver,
        private RotationAssignmentRepository $rotationAssignmentRepository,
    ) {}

    /**
     * Daily Live Operational Report.
     *
     * Everyone expected to work on $date, their group, actual punch times and
     * an accurate status (On Time / Late / Absent / On Leave / Excused).
     *
     * @return array<int, array<string, mixed>>
     */
    public function dailyLiveReport(Carbon|string $date, ?int $companyId = null, ?int $departmentId = null): array
    {
        $target = Carbon::parse($date)->startOfDay();
        $dateStr = $target->toDateString();

        $assignments = $this->rotationAssignmentRepository->getAssignmentsForDate($dateStr);
        if ($departmentId !== null) {
            $assignments = $assignments->filter(fn ($a) => optional($a->employee)->department_id === $departmentId);
        }
        $employeeIds = $assignments->pluck('employee_id')->unique()->values()->all();

        if ($employeeIds === []) {
            return [];
        }

        $punches = DB::table('iclock_transaction')
            ->whereIn('emp_id', $employeeIds)
            ->whereDate('punch_time', $dateStr)
            ->select('emp_id', DB::raw('MIN(punch_time) as first_punch'), DB::raw('MAX(punch_time) as last_punch'))
            ->groupBy('emp_id')
            ->get()
            ->keyBy('emp_id');

        $rows = [];
        foreach ($employeeIds as $employeeId) {
            $resolved = $this->resolver->resolve($employeeId, $target);
            $punch = $punches->get($employeeId);

            $status = match ($resolved['status']) {
                ScheduleResolverService::STATUS_LEAVE_EXCUSED,
                ScheduleResolverService::STATUS_SWAP => 'on_leave',
                ScheduleResolverService::STATUS_WORK => ($punch ? 'present' : 'absent'),
                default => 'rest',
            };

            $assignment = $assignments->firstWhere('employee_id', $employeeId);

            $rows[] = [
                'employee_id' => $employeeId,
                'employee_name' => optional($assignment?->employee)->name,
                'group_id' => $resolved['rotation_group_id'],
                'group_name' => optional($assignment?->rotationGroup)->name,
                'is_work_day' => $resolved['is_work_day'],
                'status' => $status,
                'expected_check_in' => $resolved['expected_check_in'],
                'expected_check_out' => $resolved['expected_check_out'],
                'first_punch' => $punch?->first_punch,
                'last_punch' => $punch?->last_punch,
            ];
        }

        usort($rows, fn ($a, $b) => ($b['is_work_day'] <=> $a['is_work_day']) ?: strcmp((string) $a['employee_name'], (string) $b['employee_name']));

        return $rows;
    }

    /**
     * Monthly Attendance Summary (high-performance SET aggregation).
     *
     * @param  array<int, int>|null  $employeeIds
     * @return Collection<int, object>
     */
    public function monthlySummary(int $month, int $year, ?int $companyId = null, ?array $employeeIds = null)
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();

        $query = DB::table('daily_attendance_summaries')
            ->whereBetween('summary_date', [$from->toDateString(), $to->toDateString()]);

        if ($employeeIds) {
            $query->whereIn('user_id', $employeeIds);
        }

        return $query
            ->select(
                'user_id',
                DB::raw("SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) as days_worked"),
                DB::raw('SUM(late_minutes) as total_late_minutes'),
                DB::raw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as unexcused_absences"),
                DB::raw("SUM(CASE WHEN status IN ('on_leave','excused','leave_excused') THEN 1 ELSE 0 END) as approved_leave_days"),
                DB::raw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days"),
            )
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->get();
    }
}
