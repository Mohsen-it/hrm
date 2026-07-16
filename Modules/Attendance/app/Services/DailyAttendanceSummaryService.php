<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\DailyAttendanceSummary;
use Modules\Attendance\Repositories\DailyAttendanceSummaryRepository;
use Modules\Shifts\Models\Shift;
use Modules\Shifts\Services\AbsenceCalculationService;
use Modules\Users\Models\User;

/**
 * DailyAttendanceSummaryService — builds the per-day roll-up for an employee.
 *
 * Given a user and a calendar date the service:
 *   1. Collects every attendance_session the user produced on that date.
 *   2. Sums up work / break / late / early-leave / overtime minutes.
 *   3. Determines the day's overall status (present | late | early_leave |
 *      missing_punch | absent | rest).
 *   4. Persists exactly one `daily_attendance_summaries` row for the (user,
 *      date) pair — enforced unique at the DB level.
 *
 * The service is intentionally stateless and free of HTTP / DB-cache state so
 * it can be reused by the auto-calculation service, queue jobs, and the
 * controllers alike.
 */
class DailyAttendanceSummaryService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private DailyAttendanceSummaryRepository $repository,
        private AbsenceCalculationService $absenceCalculationService,
    ) {}

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Fetch a summary by (user_id, date), or null when absent.
     */
    public function findForUserOnDate(int $userId, string $date): ?DailyAttendanceSummary
    {
        return $this->repository->findByUserAndDate($userId, $date);
    }

    /**
     * Find a summary by its primary key.
     */
    public function findById(int $id): ?DailyAttendanceSummary
    {
        return $this->repository->findById($id);
    }

    /**
     * Get all summaries inside a date range (inclusive).
     *
     * @return Collection<int, DailyAttendanceSummary>
     */
    public function getForDateRange(string $from, string $to, ?int $userId = null): Collection
    {
        return $this->repository
            ->query()
            ->betweenDates($from, $to)
            ->when($userId, fn ($q, $id) => $q->forUser($id))
            ->with(['user', 'shift'])
            ->orderBy('summary_date')
            ->get();
    }

    /**
     * Update the given summary with the supplied payload.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSummary(DailyAttendanceSummary $summary, array $data): DailyAttendanceSummary
    {
        $patch = [];

        foreach (['status', 'session_type', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $patch[$field] = $data[$field];
            }
        }

        if (empty($patch)) {
            return $summary;
        }

        return $this->repository->update($summary, $patch);
    }

    // ------------------------------------------------------------------
    // Calculation
    // ------------------------------------------------------------------

    /**
     * Recompute and persist the daily summary for a single user+date pair.
     *
     * @param  string  $date  The calendar date (Y-m-d) the summary belongs to.
     */
    public function recalculateForUserAndDate(int $userId, string $date): DailyAttendanceSummary
    {
        $user = User::find($userId);
        $shift = $this->resolveShift($user, $date);

        $sessions = AttendanceSession::forUser($userId)
            ->onDate($date)
            ->orderBy('check_in_at')
            ->get();

        $computed = $this->aggregateSessions($sessions, $shift, $date);

        $status = $this->resolveExternalStatus($userId, $date)
            ?? $this->determineStatus($sessions, $shift, $userId, $date);

        $payload = array_merge($computed, [
            'user_id' => $userId,
            'shift_id' => $shift?->id,
            'summary_date' => $date,
            'expected_check_in' => $shift?->start_time ? $this->formatTimeString($shift->start_time) : null,
            'expected_check_out' => $shift?->end_time ? $this->formatTimeString($shift->end_time) : null,
            'sessions_count' => $sessions->count(),
            'is_first_punch' => $sessions->isNotEmpty(),
            'is_complete' => $sessions->isNotEmpty() && $sessions->every(fn (AttendanceSession $s) => $s->check_out_at !== null),
            'status' => $status,
            'session_type' => $sessions->first()?->session_type ?? 'normal',
            'first_check_in_at' => $sessions->first()?->check_in_at,
            'last_check_out_at' => $sessions->last()?->check_out_at,
            'notes' => $sessions->pluck('notes')->filter()->implode("\n") ?: null,
            'calculated_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('daily_attendance_summaries')->updateOrInsert(
            ['user_id' => $userId, 'summary_date' => $date],
            $payload,
        );

        return DailyAttendanceSummary::forUser($userId)->onDate($date)->first();
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Sum the timing columns across every session for the day.
     *
     * @param  Collection<int, AttendanceSession>  $sessions
     * @return array<string, mixed>
     */
    protected function aggregateSessions(Collection $sessions, ?Shift $shift, string $date): array
    {
        $totalWork = 0;
        $totalBreak = 0;
        $totalOvertime = 0;
        $late = 0;
        $earlyLeave = 0;

        foreach ($sessions as $session) {
            $totalWork += (int) $session->work_minutes;
            $totalBreak += (int) $session->break_minutes;
            $totalOvertime += (int) $session->overtime_minutes;
            $late = max($late, (int) $session->late_minutes);
            $earlyLeave = max($earlyLeave, (int) $session->early_leave_minutes);
        }

        return [
            'total_work_minutes' => $totalWork,
            'total_break_minutes' => $totalBreak,
            'total_overtime_minutes' => $totalOvertime,
            'late_minutes' => $late,
            'early_leave_minutes' => $earlyLeave,
        ];
    }

    /**
     * Decide the overall day status from the collected sessions.
     *
     * @param  Collection<int, AttendanceSession>  $sessions
     */
    protected function determineStatus(Collection $sessions, ?Shift $shift, int $userId, string $date): string
    {
        // No sessions on a scheduled day → absent (unless it's a rest day).
        if ($sessions->isEmpty()) {
            if ($this->isRestDay($userId, $date)) {
                return 'rest';
            }

            return 'absent';
        }

        $hasMissing = $sessions->contains(fn (AttendanceSession $s) => $s->check_out_at === null || $s->check_in_at === null);
        $hasEarlyLeave = $sessions->contains(fn (AttendanceSession $s) => $s->early_leave_minutes > 0);
        $hasLate = $sessions->contains(fn (AttendanceSession $s) => $s->late_minutes > 0);

        if ($hasMissing) {
            return 'missing_punch';
        }

        if ($hasEarlyLeave) {
            return 'early_leave';
        }

        if ($hasLate) {
            return 'late';
        }

        return 'present';
    }

    /**
     * Check if the given date is a rest day for the employee.
     * Uses the ShiftCategory assignment and CyclicScheduleCalculator.
     */
    protected function isRestDay(int $userId, string $date): bool
    {
        if (! class_exists(AbsenceCalculationService::class)) {
            return false;
        }

        try {
            $absenceService = app(AbsenceCalculationService::class);

            return ! $absenceService->isEmployeeExpectedToWork($userId, Carbon::parse($date));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Determine whether an external module (Vacations / Holidays) has
     * already set the day's status. Returns `null` when the day belongs
     * to the regular attendance flow.
     *
     * The lookup is intentionally cheap (two table reads) so the
     * daily-rollup job stays performant. The two queries are
     * opportunistic: a missing table (module disabled) returns `null`
     * and the function falls through to the normal status logic.
     */
    protected function resolveExternalStatus(int $userId, string $date): ?string
    {
        // Holiday — applies to every employee (the `applies_to_all` flag).
        if (Schema::hasTable('holidays') && $this->isHoliday($date)) {
            return 'holiday';
        }

        // Approved vacation request overlapping the date.
        if (Schema::hasTable('user_vacation_requests') && $this->hasApprovedVacation($userId, $date)) {
            return 'vacation';
        }

        return null;
    }

    /**
     * Return true when an active holiday has a materialised date that
     * matches the supplied `Y-m-d`.
     */
    protected function isHoliday(string $date): bool
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return false;
        }

        $month = (int) date('n', $ts);
        $day = (int) date('j', $ts);
        $year = (int) date('Y', $ts);

        $exists = DB::table('holidays')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($date, $month, $day): void {
                $q->where(function ($fixed) use ($date): void {
                    $fixed->where('is_recurring', false)->where('date', $date);
                })->orWhere(function ($recur) use ($month, $day): void {
                    $recur->where('is_recurring', true)
                        ->where('recurring_month', $month)
                        ->where('recurring_day', $day);
                });
            })
            ->exists();

        return $exists;
    }

    /**
     * Return true when the user has an approved vacation request that
     * covers the supplied date.
     */
    protected function hasApprovedVacation(int $userId, string $date): bool
    {
        return DB::table('user_vacation_requests')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }

    /**
     * Resolve the effective shift for a user on a given date.
     *
     * Mirrors the precedence used by the AttendanceSessionService so the
     * summary references the same shift the sessions were created against.
     */
    protected function resolveShift(?User $user, string $date): ?Shift
    {
        if (! $user) {
            return null;
        }

        if ($user->shift_id) {
            return Shift::find($user->shift_id);
        }

        // Resolve through a pivot subquery so the boolean precedence
        // between `effective_from`, `effective_to IS NULL` and
        // `effective_to >= date` is enforced correctly. The previous
        // `wherePivot -> orWherePivot` chain widened the predicate and
        // could return pivots whose `effective_from` was in the future.
        $pivotSub = DB::table('user_shifts')
            ->select('shift_id')
            ->where('user_id', $user->id)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });

        $shiftId = DB::table('shifts')
            ->whereIn('id', $pivotSub)
            ->orderByDesc(DB::raw('(SELECT is_primary FROM user_shifts WHERE user_shifts.shift_id = shifts.id AND user_shifts.user_id = '.(int) $user->id.' LIMIT 1)'))
            ->value('id');

        if (! $shiftId) {
            return null;
        }

        return Shift::find($shiftId);
    }

    /**
     * Normalize any time-like value into a `H:i:s` string.
     */
    protected function formatTimeString(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        $value = (string) $value;

        return strlen($value) >= 8 ? substr($value, 0, 8) : $value;
    }
}
