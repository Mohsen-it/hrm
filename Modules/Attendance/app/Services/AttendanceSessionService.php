<?php

namespace Modules\Attendance\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Repositories\AttendanceSessionRepository;
use Modules\Attendance\Repositories\RawAttendanceLogRepository;
use Modules\Shifts\Models\Shift;
use Modules\Users\Models\User;

/**
 * AttendanceSessionService — orchestrates check-in / check-out sessions.
 *
 * Responsibilities:
 *  - Resolve the expected shift for an employee on a given date.
 *  - Create / extend attendance sessions from device punches or manual entry.
 *  - Guard against duplicate punches inside the configured grace window.
 *  - Populate timing metrics (work / late / early-leave / overtime) on each
 *    session so daily summaries can simply aggregate them.
 *
 * This service deliberately depends on `RawAttendanceLogRepository` (only) to
 * avoid a circular dependency on `DailyAttendanceSummaryService`. Daily roll
 * ups are produced by `DailyAttendanceAutoCalculationService`.
 */
class AttendanceSessionService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private RawAttendanceLogRepository $rawLogRepository,
        private AttendanceSessionRepository $sessionRepository,
    ) {}

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Find an attendance session by its primary key.
     */
    public function findSession(int $id): ?AttendanceSession
    {
        return AttendanceSession::with(['user', 'shift', 'rawLog'])
            ->find($id);
    }

    /**
     * Get the latest open (no check-out yet) session for the given user.
     */
    public function getOpenSessionForUser(int $userId): ?AttendanceSession
    {
        return AttendanceSession::forUser($userId)
            ->open()
            ->latest('check_in_at')
            ->first();
    }

    /**
     * Get a paginated list of sessions filtered by the supplied filter bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllSessions(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->sessionRepository->getAll($filters, $perPage);
    }

    /**
     * Update an existing session with the supplied payload.
     *
     * Only the operator-editable columns are accepted; the service recomputes
     * the timing columns when the check-in / check-out timestamps change.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSession(AttendanceSession $session, array $data): AttendanceSession
    {
        $patch = [];

        foreach (['shift_id', 'attendance_date', 'check_in_at', 'check_out_at', 'session_type', 'source', 'notes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $patch[$field] = $data[$field];
            }
        }

        if (empty($patch)) {
            return $session->fresh(['user', 'shift']);
        }

        $session->forceFill($patch)->save();

        if (array_key_exists('check_out_at', $patch) && $patch['check_out_at']) {
            return $this->closeSession($session, new DateTimeImmutable((string) $patch['check_out_at']));
        }

        if (array_key_exists('check_in_at', $patch) && $patch['check_in_at']) {
            $at = new DateTimeImmutable((string) $patch['check_in_at']);
            $lateMinutes = $this->computeLateMinutes($session->expected_check_in, $at, $session->shift);
            $session->forceFill(['late_minutes' => $lateMinutes])->save();
        }

        return $session->fresh(['user', 'shift']);
    }

    /**
     * Soft delete the given session.
     */
    public function deleteSession(AttendanceSession $session): bool
    {
        return $this->sessionRepository->delete($session);
    }

    // ------------------------------------------------------------------
    // Core operations
    // ------------------------------------------------------------------

    /**
     * Register a check-in for the given user.
     *
     * Punches arriving inside {@see self::DUPLICATE_WINDOW_MINUTES} of the
     * most recent session for the same user are treated as duplicates and
     * the existing session is returned untouched.
     *
     * @param  array<string, mixed>  $context  {
     *
     * @type int|null $shift_id       Force a specific shift id.
     * @type int|null $device_id      Originating device id.
     * @type int|null $raw_log_id     Originating raw log id.
     * @type int|null $zone_id        Zone id where punch happened.
     * @type string|null $source         Punch source (device|manual|api|adms|bio).
     * @type string|null $session_type   Session type (normal|overtime|make_up).
     * @type string|null $ip_address     Source IP.
     * @type array|null $metadata       Extra metadata.
     * @type string|null $notes          Operator notes.
     * @type int|null $created_by     User id that recorded the punch.
     *                }
     *
     * @throws InvalidArgumentException When the user or shift cannot be resolved.
     */
    public function checkIn(int $userId, DateTimeInterface $at, array $context = []): AttendanceSession
    {
        $user = User::find($userId);
        if (! $user) {
            throw new InvalidArgumentException("User [{$userId}] not found.");
        }

        $at = $this->normalizeDateTime($at);

        $duplicate = $this->detectDuplicatePunch($userId, $at);
        if ($duplicate !== null) {
            return $duplicate;
        }

        $shiftId = isset($context['shift_id']) ? (int) $context['shift_id'] : null;
        $shift = $this->resolveShift($user, $at->format('Y-m-d'), $shiftId);

        $expected = $this->computeExpectedSlots($shift, $at);
        $lateMinutes = $this->computeLateMinutes($expected['check_in'], $at, $shift);

        $session = new AttendanceSession;
        $session->forceFill([
            'user_id' => $user->id,
            'shift_id' => $shift?->id,
            'device_id' => $context['device_id'] ?? null,
            'raw_log_id' => $context['raw_log_id'] ?? null,
            'zone_id' => $context['zone_id'] ?? null,
            'attendance_date' => $at->format('Y-m-d'),
            'check_in_at' => $at,
            'expected_check_in' => $expected['check_in'],
            'expected_check_out' => $expected['check_out'],
            'status' => $lateMinutes > 0 ? 'late' : 'present',
            'session_type' => $context['session_type'] ?? 'normal',
            'source' => $context['source'] ?? 'device',
            'late_minutes' => $lateMinutes,
            'ip_address' => $context['ip_address'] ?? null,
            'metadata' => $context['metadata'] ?? null,
            'notes' => $context['notes'] ?? null,
            'created_by' => $context['created_by'] ?? null,
        ])->save();

        return $session->fresh(['user', 'shift']);
    }

    /**
     * Register a check-out for the given user.
     *
     * If an open session exists for the user it is closed; otherwise the
     * punch is recorded as a fresh session with only `check_out_at` set,
     * which the daily auto-calculation job will revisit (status = missing_punch).
     *
     * @param  array<string, mixed>  $context
     *
     * @throws InvalidArgumentException When the user cannot be resolved.
     */
    public function checkOut(int $userId, DateTimeInterface $at, array $context = []): AttendanceSession
    {
        $user = User::find($userId);
        if (! $user) {
            throw new InvalidArgumentException("User [{$userId}] not found.");
        }

        $at = $this->normalizeDateTime($at);

        $session = $this->getOpenSessionForUser($userId);

        if (! $session) {
            $shiftId = isset($context['shift_id']) ? (int) $context['shift_id'] : null;
            $shift = $this->resolveShift($user, $at->format('Y-m-d'), $shiftId);
            $expected = $this->computeExpectedSlots($shift, $at);

            $session = new AttendanceSession;
            $session->forceFill([
                'user_id' => $user->id,
                'shift_id' => $shift?->id,
                'device_id' => $context['device_id'] ?? null,
                'raw_log_id' => $context['raw_log_id'] ?? null,
                'zone_id' => $context['zone_id'] ?? null,
                'attendance_date' => $at->format('Y-m-d'),
                'check_in_at' => null,
                'check_out_at' => $at,
                'expected_check_in' => $expected['check_in'],
                'expected_check_out' => $expected['check_out'],
                'status' => 'missing_punch',
                'session_type' => $context['session_type'] ?? 'normal',
                'source' => $context['source'] ?? 'device',
                'work_minutes' => 0,
                'ip_address' => $context['ip_address'] ?? null,
                'metadata' => $context['metadata'] ?? null,
                'notes' => $context['notes'] ?? null,
                'created_by' => $context['created_by'] ?? null,
            ])->save();

            return $session->fresh(['user', 'shift']);
        }

        return $this->closeSession($session, $at, $context);
    }

    /**
     * Close an open attendance session and recompute its timing metrics.
     *
     * @param  array<string, mixed>  $context
     */
    public function closeSession(AttendanceSession $session, DateTimeInterface $at, array $context = []): AttendanceSession
    {
        $at = $this->normalizeDateTime($at);
        $checkIn = $session->check_in_at ? $this->normalizeDateTime($session->check_in_at) : null;

        $breakMinutes = (int) ($session->break_minutes ?? ($session->shift?->break_minutes ?? 0));
        $workMinutes = $checkIn ? max(0, $this->minutesBetween($checkIn, $at) - $breakMinutes) : 0;

        $earlyLeaveMinutes = 0;
        $overtimeMinutes = 0;

        $expectedEnd = $session->expected_check_out
            ? $this->buildDateTimeFromSlot($session->expected_check_out, $session->attendance_date, $session)
            : null;

        if ($expectedEnd !== null) {
            $diff = (int) round(($at->getTimestamp() - $expectedEnd->getTimestamp()) / 60);
            if ($diff < 0) {
                $earlyLeaveMinutes = abs($diff);
            } elseif ($diff > 0) {
                $overtimeMinutes = $diff;
            }
        }

        $status = $session->status;
        if (! in_array($status, ['holiday', 'vacation', 'weekend'], true)) {
            if ($earlyLeaveMinutes > 0) {
                $status = 'early_leave';
            } elseif ($session->late_minutes > 0) {
                $status = $status === 'missing_punch' ? 'missing_punch' : 'late';
            } else {
                $status = 'present';
            }
        }

        $session->forceFill([
            'check_out_at' => $at,
            'work_minutes' => $workMinutes,
            'break_minutes' => $breakMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'status' => $status,
        ])->save();

        // stamp operational metadata from the punch (optional overrides)
        $this->mergeRawContext($session, $context);

        return $session->fresh(['user', 'shift']);
    }

    // ------------------------------------------------------------------
    // Pipeline — raw log ingestion
    // ------------------------------------------------------------------

    /**
     * Convert one raw attendance log into (or onto) a session.
     *
     * Returns null when the log has an unknown punch type (no action taken).
     */
    public function processRawLog(RawAttendanceLog $log): ?AttendanceSession
    {
        if (! $log->user_id) {
            return null;
        }

        $at = $this->normalizeDateTime($log->punch_time);

        $context = [
            'device_id' => $log->device_id,
            'raw_log_id' => $log->id,
            'source' => $log->source,
            'metadata' => $log->raw_data,
            'ip_address' => $log->ip_address,
        ];

        $session = match ($log->punch_type) {
            'check_in' => $this->checkIn($log->user_id, $at, $context),
            'check_out' => $this->checkOut($log->user_id, $at, $context),
            default => null,
        };

        if ($session !== null) {
            $this->rawLogRepository->markProcessed([$log->id], $at);
        }

        return $session;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Number of minutes inside which an incoming punch is considered duplicate.
     */
    protected function duplicateWindowMinutes(): int
    {
        return (int) config('attendance.duplicate_window_minutes', 1);
    }

    /**
     * Detect a duplicate punch within the configured window.
     *
     * Returns the most recent overlapping session, if any.
     */
    protected function detectDuplicatePunch(int $userId, DateTimeInterface $at): ?AttendanceSession
    {
        $window = $this->duplicateWindowMinutes();
        $threshold = (clone $at)->modify("-{$window} minutes");

        $existing = AttendanceSession::forUser($userId)
            ->whereBetween('check_in_at', [$threshold, $at])
            ->orderByDesc('check_in_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return AttendanceSession::forUser($userId)
            ->whereBetween('check_out_at', [$threshold, $at])
            ->orderByDesc('check_out_at')
            ->first();
    }

    /**
     * Resolve the effective shift for a user on a given date.
     *
     * Precedence:
     *   1. Explicit shift_id passed by the caller (force).
     *   2. The user's primary `shift_id` column.
     *   3. The currently effective `user_shifts` pivot row for the date.
     *   4. null (off-day / unscheduled).
     */
    protected function resolveShift(User $user, string $date, ?int $forcedShiftId = null): ?Shift
    {
        if ($forcedShiftId) {
            return Shift::find($forcedShiftId);
        }

        if ($user->shift_id) {
            return $user->shift;
        }

        // Use a subquery against the `user_shifts` pivot so the boolean
        // precedence is well-defined; mixing `wherePivot` / `orWherePivot`
        // on the same relation chain produced an OR-broadened predicate
        // that returned pivots whose `effective_from` was in the future.
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
     * Compute the expected `check_in` / `check_out` slots for the supplied shift.
     *
     * The returned strings are full `Y-m-d H:i:s` timestamps so the full
     * overnight semantics are encoded for the day the session started in.
     *
     * @return array{check_in: string|null, check_out: string|null}
     */
    protected function computeExpectedSlots(?Shift $shift, DateTimeInterface $at): array
    {
        if (! $shift || ! $shift->start_time || ! $shift->end_time) {
            return ['check_in' => null, 'check_out' => null];
        }

        $date = $at->format('Y-m-d');

        $start = $shift->start_time instanceof DateTimeInterface
            ? $shift->start_time->format('H:i:s')
            : (string) $shift->start_time;

        $end = $shift->end_time instanceof DateTimeInterface
            ? $shift->end_time->format('H:i:s')
            : (string) $shift->end_time;

        return [
            'check_in' => $start,
            'check_out' => $end,
        ];
    }

    /**
     * Reconstruct the absolute date-time of an `expected_checkout` column,
     * attaching the session's attendance date and applying overnight roll-over.
     */
    protected function buildDateTimeFromSlot(string $slot, string $attendanceDate, AttendanceSession $session): ?DateTimeImmutable
    {
        // The persisted slot is always the `H:i:s` portion of the expected time.
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $slot) === 1) {
            $slot = "{$attendanceDate} {$slot}";
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $slot)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $slot)
            ?: null;

        if ($dt !== null && $session->check_in_at !== null) {
            $checkIn = $this->normalizeDateTime($session->check_in_at);
            if ($dt <= $checkIn) {
                $dt = $dt->modify('+1 day');
            }
        }

        return $dt;
    }

    /**
     * Compute late minutes for a check-in against the expected slot.
     *
     * Grace minutes from the shift (or `attendance.default_grace_minutes`)
     * are subtracted first; punches within the grace window never count as
     * late.
     *
     * @param  string|null  $expectedCheckIn  A `Y-m-d H:i:s` slot.
     */
    protected function computeLateMinutes(?string $expectedCheckIn, DateTimeInterface $at, ?Shift $shift): int
    {
        if (! $expectedCheckIn) {
            return 0;
        }

        $expected = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $expectedCheckIn);
        if (! $expected || $at <= $expected) {
            return 0;
        }

        $grace = (int) ($shift?->grace_minutes ?? config('attendance.default_grace_minutes', 0));
        $minutes = (int) round(($at->getTimestamp() - $expected->getTimestamp()) / 60);

        return max(0, $minutes - $grace);
    }

    /**
     * Compute the number of whole minutes between two date-times (always positive).
     */
    protected function minutesBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return abs((int) round(($to->getTimestamp() - $from->getTimestamp()) / 60));
    }

    /**
     * Normalize any date-time input to an immutable instance in the app timezone.
     */
    protected function normalizeDateTime(DateTimeInterface|string $at): DateTimeImmutable
    {
        if ($at instanceof DateTimeImmutable) {
            return $at;
        }

        if ($at instanceof \DateTime) {
            return DateTimeImmutable::createFromMutable($at);
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $at)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $at)
            ?: @new DateTimeImmutable($at);

        return $parsed;
    }

    /**
     * Merge optional punch context ($raw_log_id / notes / created_by etc.) on
     * an already-saved session without overwriting computed values.
     *
     * @param  array<string, mixed>  $context
     */
    protected function mergeRawContext(AttendanceSession $session, array $context): void
    {
        $patch = [];

        foreach (['raw_log_id', 'zone_id', 'created_by'] as $field) {
            if (array_key_exists($field, $context) && $session->getAttribute($field) === null) {
                $patch[$field] = $context[$field];
            }
        }

        if (array_key_exists('notes', $context) && $session->notes === null && $context['notes'] !== null) {
            $patch['notes'] = $context['notes'];
        }

        if (array_key_exists('ip_address', $context) && $session->ip_address === null && $context['ip_address'] !== null) {
            $patch['ip_address'] = $context['ip_address'];
        }

        if (array_key_exists('metadata', $context) && $session->metadata === null && $context['metadata'] !== null) {
            $patch['metadata'] = $context['metadata'];
        }

        if (! empty($patch)) {
            $session->forceFill($patch)->save();
        }
    }
}
