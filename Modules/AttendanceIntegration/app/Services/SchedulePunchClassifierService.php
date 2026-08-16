<?php

namespace Modules\AttendanceIntegration\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\Shifts\Services\ScheduleResolverService;

/**
 * Classifies an otherwise ambiguous device punch using its employee rotation.
 *
 * Rotation margins are stored as absolute daily windows. For example, an
 * entry window of 07:00-12:00 and an exit window of 14:30-18:00 classifies a
 * 15:00 punch as check-out even when a device reports its default state.
 */
class SchedulePunchClassifierService
{
    /** @var array<string, array<string, mixed>> */
    private array $scheduleCache = [];

    public function __construct(
        private ScheduleResolverService $scheduleResolver,
    ) {}

    /**
     * Classify a punch by its assigned rotation windows.
     *
     * The original device classification is retained only when the employee
     * has no configured punch windows at all (unassigned employees, rotations
     * without a schedule). Once windows exist, a punch outside every window is
     * deliberately Unknown: an early-morning punch on a rest day is the
     * previous overnight duty's check-out, never a new phantom session.
     */
    public function classify(
        ?int $userId,
        DateTimeInterface $punchedAt,
        PunchType $fallback,
    ): PunchType {
        if (! $userId) {
            return $fallback;
        }

        $timestamp = $punchedAt instanceof DateTimeImmutable
            ? $punchedAt
            : DateTimeImmutable::createFromInterface($punchedAt);

        $matches = [];
        $hasConfiguredWindow = false;

        foreach ([$timestamp->format('Y-m-d'), $timestamp->modify('-1 day')->format('Y-m-d')] as $date) {
            $schedule = $this->resolveSchedule($userId, $date);

            foreach (['in_ahead_margin', 'in_above_margin', 'out_ahead_margin', 'out_above_margin'] as $edge) {
                if (($schedule[$edge] ?? null) !== null && ($schedule[$edge] ?? '') !== '') {
                    $hasConfiguredWindow = true;
                    break;
                }
            }

            if (! ($schedule['is_work_day'] ?? false)) {
                continue;
            }

            if ($this->isWithinWindow($timestamp, $date, $schedule['in_ahead_margin'] ?? null, $schedule['in_above_margin'] ?? null)) {
                $matches[] = PunchType::CheckIn;
            }

            if ($this->isWithinWindow($timestamp, $date, $schedule['out_ahead_margin'] ?? null, $schedule['out_above_margin'] ?? null)) {
                $matches[] = PunchType::CheckOut;
            }

            // Overnight duties end on the departure morning — the first rest
            // day after the duty. The morning punch of that day is the
            // previous duty's check-out (schedule out_time ± margins anchored
            // on the next day), not a new attendance.
            $nextDayAhead = $schedule['next_day_out_ahead_margin'] ?? null;
            $nextDayAbove = $schedule['next_day_out_above_margin'] ?? null;

            if (($schedule['is_overnight'] ?? false) && $nextDayAhead && $nextDayAbove) {
                $departureDate = $this->nextDay($date);
                $departure = $this->resolveSchedule($userId, $departureDate);

                if (! ($departure['is_work_day'] ?? false)
                    && $this->isWithinWindow($timestamp, $departureDate, $nextDayAhead, $nextDayAbove)) {
                    $matches[] = PunchType::CheckOut;
                }
            }
        }

        $matches = array_values(array_unique(array_map(fn (PunchType $type) => $type->value, $matches)));

        return match (count($matches)) {
            0 => $hasConfiguredWindow ? PunchType::Unknown : $fallback,
            1 => PunchType::from($matches[0]),
            default => PunchType::Unknown,
        };
    }

    /**
     * The next calendar day after a Y-m-d date.
     */
    private function nextDay(string $date): string
    {
        return (new DateTimeImmutable($date.' 00:00:00'))->modify('+1 day')->format('Y-m-d');
    }

    /** @return array<string, mixed> */
    private function resolveSchedule(int $userId, string $date): array
    {
        $key = "{$userId}:{$date}";

        return $this->scheduleCache[$key] ??= $this->scheduleResolver->resolve($userId, $date);
    }

    private function isWithinWindow(DateTimeImmutable $timestamp, string $date, ?string $start, ?string $end): bool
    {
        if (! $start || ! $end) {
            return false;
        }

        $windowStart = $this->atDate($date, $start);
        $windowEnd = $this->atDate($date, $end);
        if (! $windowStart || ! $windowEnd) {
            return false;
        }

        if ($windowEnd < $windowStart) {
            $windowEnd = $windowEnd->modify('+1 day');
        }

        return $timestamp >= $windowStart && $timestamp <= $windowEnd;
    }

    private function atDate(string $date, string $time): ?DateTimeImmutable
    {
        $normalized = substr($time, 0, 8);

        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', "{$date} {$normalized}")
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', "{$date} ".substr($normalized, 0, 5))
            ?: null;
    }
}
