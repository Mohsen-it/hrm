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
     * The original device classification is retained when no configured window
     * matches. This keeps devices and rotations without window configuration
     * backward compatible.
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
        foreach ([$timestamp->format('Y-m-d'), $timestamp->modify('-1 day')->format('Y-m-d')] as $date) {
            $schedule = $this->resolveSchedule($userId, $date);
            if (! ($schedule['is_work_day'] ?? false)) {
                continue;
            }

            if ($this->isWithinWindow($timestamp, $date, $schedule['in_ahead_margin'] ?? null, $schedule['in_above_margin'] ?? null)) {
                $matches[] = PunchType::CheckIn;
            }

            if ($this->isWithinWindow($timestamp, $date, $schedule['out_ahead_margin'] ?? null, $schedule['out_above_margin'] ?? null)) {
                $matches[] = PunchType::CheckOut;
            }
        }

        $matches = array_values(array_unique(array_map(fn (PunchType $type) => $type->value, $matches)));

        return match (count($matches)) {
            0 => $fallback,
            1 => PunchType::from($matches[0]),
            default => PunchType::Unknown,
        };
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
