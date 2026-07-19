<?php

namespace Modules\Attendance\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Repositories\AttendanceSessionRepository;
use Modules\Attendance\Repositories\RawAttendanceLogRepository;
use Modules\Shifts\Services\ScheduleResolverService;
use Modules\Users\Models\User;

/**
 * AttendanceSessionService — orchestrates check-in / check-out sessions.
 *
 * Responsibilities:
 *  - Resolve the expected schedule for an employee on a given date via ScheduleResolverService.
 *  - Create / extend attendance sessions from device punches or manual entry.
 *  - Guard against duplicate punches inside the configured grace window.
 *  - Populate timing metrics (work / late / early-leave / overtime) on each
 *    session so daily summaries can simply aggregate them.
 */
class AttendanceSessionService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private RawAttendanceLogRepository $rawLogRepository,
        private AttendanceSessionRepository $sessionRepository,
        private ScheduleResolverService $scheduleResolver,
    ) {}

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Find an attendance session by its primary key.
     */
    public function findSession(int $id): ?AttendanceSession
    {
        return AttendanceSession::with(['user', 'rawLog'])
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
     * @param  array<string, mixed>  $data
     */
    public function updateSession(AttendanceSession $session, array $data): AttendanceSession
    {
        $patch = [];

        foreach (['attendance_date', 'check_in_at', 'check_out_at', 'session_type', 'source', 'notes'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $patch[$field] = $data[$field];
            }
        }

        if (empty($patch)) {
            return $session->fresh(['user']);
        }

        $session->forceFill($patch)->save();

        if (array_key_exists('check_out_at', $patch) && $patch['check_out_at']) {
            return $this->closeSession($session, new DateTimeImmutable((string) $patch['check_out_at']));
        }

        if (array_key_exists('check_in_at', $patch) && $patch['check_in_at']) {
            $at = new DateTimeImmutable((string) $patch['check_in_at']);
            $resolved = $this->scheduleResolver->resolve($session->user_id, $session->attendance_date);
            $lateMinutes = $this->computeLateMinutes($resolved['expected_check_in'], $at, $resolved);
            $session->forceFill(['late_minutes' => $lateMinutes])->save();
        }

        return $session->fresh(['user']);
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
     * @param  array<string, mixed>  $context  {
     *
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
     * @throws InvalidArgumentException When the user cannot be resolved.
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

        $dateStr = $at->format('Y-m-d');
        $resolved = $this->scheduleResolver->resolve($userId, $dateStr);

        // If employee is on rest day or unassigned, still create the session
        // but without expected times (status will be determined by daily summary)
        $expectedIn = $resolved['expected_check_in'] ? "{$dateStr} {$resolved['expected_check_in']}:00" : null;
        $expectedOut = $resolved['expected_check_out'] ? "{$dateStr} {$resolved['expected_check_out']}:00" : null;

        $lateMinutes = $this->computeLateMinutes($expectedIn, $at, $resolved);

        $session = new AttendanceSession;
        $session->forceFill([
            'user_id' => $user->id,
            'device_id' => $context['device_id'] ?? null,
            'raw_log_id' => $context['raw_log_id'] ?? null,
            'zone_id' => $context['zone_id'] ?? null,
            'attendance_date' => $dateStr,
            'check_in_at' => $at,
            'expected_check_in' => $resolved['expected_check_in'],
            'expected_check_out' => $resolved['expected_check_out'],
            'status' => $lateMinutes > 0 ? 'late' : 'present',
            'session_type' => $context['session_type'] ?? 'normal',
            'source' => $context['source'] ?? 'device',
            'late_minutes' => $lateMinutes,
            'ip_address' => $context['ip_address'] ?? null,
            'metadata' => $context['metadata'] ?? null,
            'notes' => $context['notes'] ?? null,
            'created_by' => $context['created_by'] ?? null,
        ])->save();

        return $session->fresh(['user']);
    }

    /**
     * Register a check-out for the given user.
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
            $dateStr = $at->format('Y-m-d');
            $resolved = $this->scheduleResolver->resolve($userId, $dateStr);

            $session = new AttendanceSession;
            $session->forceFill([
                'user_id' => $user->id,
                'device_id' => $context['device_id'] ?? null,
                'raw_log_id' => $context['raw_log_id'] ?? null,
                'zone_id' => $context['zone_id'] ?? null,
                'attendance_date' => $dateStr,
                'check_in_at' => null,
                'check_out_at' => $at,
                'expected_check_in' => $resolved['expected_check_in'],
                'expected_check_out' => $resolved['expected_check_out'],
                'status' => 'missing_punch',
                'session_type' => $context['session_type'] ?? 'normal',
                'source' => $context['source'] ?? 'device',
                'work_minutes' => 0,
                'ip_address' => $context['ip_address'] ?? null,
                'metadata' => $context['metadata'] ?? null,
                'notes' => $context['notes'] ?? null,
                'created_by' => $context['created_by'] ?? null,
            ])->save();

            return $session->fresh(['user']);
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

        $workMinutes = $checkIn ? max(0, $this->minutesBetween($checkIn, $at)) : 0;

        $earlyLeaveMinutes = 0;
        $overtimeMinutes = 0;

        // Resolve expected times from rotation via resolver
        $resolved = $this->scheduleResolver->resolve($session->user_id, $session->attendance_date);
        $expectedCheckOut = $resolved['expected_check_out'];

        if ($expectedCheckOut) {
            $expectedEnd = $this->buildDateTimeFromSlot($expectedCheckOut, $session->attendance_date);
            if ($expectedEnd !== null) {
                $diff = (int) round(($at->getTimestamp() - $expectedEnd->getTimestamp()) / 60);
                if ($diff < 0) {
                    $earlyLeaveMinutes = abs($diff);
                } elseif ($diff > 0) {
                    $overtimeMinutes = $diff;
                }
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
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'status' => $status,
        ])->save();

        $this->mergeRawContext($session, $context);

        return $session->fresh(['user']);
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
     * Reconstruct the absolute date-time of an expected check-out slot.
     */
    protected function buildDateTimeFromSlot(string $slot, string $attendanceDate): ?DateTimeImmutable
    {
        if (preg_match('/^\d{2}:\d{2}$/', $slot) === 1) {
            $slot = "{$attendanceDate} {$slot}:00";
        } elseif (preg_match('/^\d{2}:\d{2}:\d{2}$/', $slot) === 1) {
            $slot = "{$attendanceDate} {$slot}";
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $slot)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $slot)
            ?: null;

        return $dt;
    }

    /**
     * Compute late minutes for a check-in against the expected slot.
     *
     * Grace minutes from the resolver are subtracted first; punches within
     * the grace window never count as late.
     */
    protected function computeLateMinutes(?string $expectedCheckIn, DateTimeInterface $at, array $resolved): int
    {
        if (! $expectedCheckIn) {
            return 0;
        }

        $expected = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $expectedCheckIn);
        if (! $expected || $at <= $expected) {
            return 0;
        }

        // Grace minutes from the rotation group or global config
        $grace = (int) config('attendance.default_grace_minutes', 0);
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
     * Merge optional punch context on an already-saved session.
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
