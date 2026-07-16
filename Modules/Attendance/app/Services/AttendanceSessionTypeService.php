<?php

namespace Modules\Attendance\Services;

use InvalidArgumentException;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Repositories\AttendanceSessionRepository;

/**
 * AttendanceSessionTypeService — central rules for the `session_type` column.
 *
 * The Attendance domain recognises three logical session types:
 *   - `normal`     : the standard check-in / check-out pair.
 *   - `overtime`   : extra time worked beyond the shift.
 *   - `make_up`    : compensatory time recorded outside the normal schedule.
 *
 * The service keeps the canonical list, validates incoming payloads, and
 * applies the business rules that decide which type a session should
 * receive (e.g. force a check-in to be tagged as `overtime` when the user
 * was supposed to be off that day, but still punched).
 *
 * Everything is read-mostly; the service mutates the session it is given
 * and returns the fresh instance (or null when the change is rejected).
 */
class AttendanceSessionTypeService
{
    /**
     * The session types the module recognises.
     *
     * @var array<int, string>
     */
    public const TYPES = ['normal', 'overtime', 'make_up'];

    /**
     * Default type applied when none is supplied.
     */
    public const DEFAULT_TYPE = 'normal';

    /**
     * Create a new service instance.
     */
    public function __construct(
        private AttendanceSessionRepository $repository,
    ) {}

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * Determine whether the supplied type is a known session type.
     */
    public function isValid(?string $type): bool
    {
        return $type !== null && in_array($type, self::TYPES, true);
    }

    /**
     * Return the canonical session type, defaulting when the input is empty.
     *
     * @throws InvalidArgumentException When the supplied type is not in the
     *                                  known list (and not empty).
     */
    public function normalize(?string $type): string
    {
        if ($type === null || $type === '') {
            return self::DEFAULT_TYPE;
        }

        if (! $this->isValid($type)) {
            throw new InvalidArgumentException("Unknown session_type [{$type}].");
        }

        return $type;
    }

    /**
     * Return every session type the UI should expose.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array
    {
        $out = [];
        foreach (self::TYPES as $type) {
            $out[] = [
                'value' => $type,
                'label' => __('attendance.session_type.'.$type),
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Mutations
    // ------------------------------------------------------------------

    /**
     * Update the `session_type` of an existing session.
     *
     * @throws InvalidArgumentException When the requested type is unknown.
     */
    public function updateType(AttendanceSession $session, string $type): AttendanceSession
    {
        $normalized = $this->normalize($type);

        if ($session->session_type === $normalized) {
            return $session;
        }

        $session->forceFill(['session_type' => $normalized])->save();

        return $session->fresh(['user', 'shift']);
    }

    /**
     * Bulk-update the `session_type` of the supplied sessions.
     *
     * Sessions already carrying the new type are skipped; the rest are updated
     * through the repository so the change is committed in a single pass.
     *
     * @param  array<int, int>  $sessionIds
     * @return int Number of sessions that were actually updated
     *
     * @throws InvalidArgumentException When the requested type is unknown.
     */
    public function bulkUpdateType(array $sessionIds, string $type): int
    {
        $normalized = $this->normalize($type);

        $sessions = $this->repository->getByIds($sessionIds);
        $pending = $sessions
            ->filter(fn (AttendanceSession $s) => $s->session_type !== $normalized)
            ->pluck('id')
            ->all();

        if (empty($pending)) {
            return 0;
        }

        return $this->repository->updateType($pending, $normalized);
    }

    // ------------------------------------------------------------------
    // Business rules
    // ------------------------------------------------------------------

    /**
     * Decide the session type that an incoming check-in punch should be tagged
     * with given the surrounding context.
     *
     * The decision tree is:
     *   1. Respect an explicit `session_type` from the caller (force).
     *   2. If the user has no effective shift on that day, fall back to
     *      `make_up` (compensatory time).
     *   3. If the user is on holiday / vacation / weekend per the supplied
     *      flags, fall back to `make_up`.
     *   4. If the user is punching well past the end of the shift, default
     *      to `overtime`.
     *   5. Otherwise use the normal default.
     *
     * @param  array<string, mixed>  $context  {
     *
     * @type string|null $session_type  Explicit type override
     * @type bool $is_holiday
     * @type bool $is_vacation
     * @type bool $is_weekend
     * @type int|null $shift_end_offset_minutes
     *                }
     */
    public function resolveTypeForCheckIn(array $context = []): string
    {
        $explicit = $context['session_type'] ?? null;
        if ($this->isValid($explicit)) {
            return $explicit;
        }

        if (! empty($context['is_holiday']) || ! empty($context['is_vacation']) || ! empty($context['is_weekend'])) {
            return 'make_up';
        }

        $offset = (int) ($context['shift_end_offset_minutes'] ?? 0);
        if ($offset > (int) config('attendance.overtime_grace_minutes', 60)) {
            return 'overtime';
        }

        return self::DEFAULT_TYPE;
    }
}
