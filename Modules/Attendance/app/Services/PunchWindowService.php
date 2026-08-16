<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Modules\Shifts\Services\ScheduleResolverService;

/**
 * Classifies a device punch from the configured rotation time windows.
 */
class PunchWindowService
{
    /** @var array<string, array<string, mixed>> */
    private array $scheduleCache = [];

    public function __construct(
        private ScheduleResolverService $scheduleResolver,
    ) {}

    /**
     * Determine whether a punch belongs to the check-in or check-out window.
     *
     * @return array{type: ?string, has_configured_window: bool}
     */
    public function classify(int $employeeId, DateTimeInterface $punchAt, bool $preferCheckOut = false): array
    {
        $punchAt = CarbonImmutable::instance($punchAt);
        $hasConfiguredWindow = false;
        $matchingTypes = [];

        // The previous day is included for checkout windows that cross midnight
        // and for overnight duties whose check-out lands on the next morning.
        foreach ([$punchAt->toDateString(), $punchAt->subDay()->toDateString()] as $scheduleDate) {
            $schedule = $this->schedule($employeeId, $scheduleDate);

            foreach (['in_ahead_margin', 'in_above_margin', 'out_ahead_margin', 'out_above_margin'] as $edge) {
                if (($schedule[$edge] ?? null) !== null && ($schedule[$edge] ?? '') !== '') {
                    $hasConfiguredWindow = true;
                    break;
                }
            }

            if (! ($schedule['is_work_day'] ?? false)) {
                continue;
            }

            foreach ([
                'check_in' => ['in_ahead_margin', 'in_above_margin'],
                'check_out' => ['out_ahead_margin', 'out_above_margin'],
            ] as $type => [$startKey, $endKey]) {
                $start = $schedule[$startKey] ?? null;
                $end = $schedule[$endKey] ?? null;
                if (! $start || ! $end) {
                    continue;
                }

                if ($this->isWithinWindow($punchAt, $scheduleDate, $start, $end)) {
                    $matchingTypes[] = $type;
                }
            }

            // Overnight duties end on the departure morning — the first rest
            // day after the duty. The morning punch of that day closes the
            // previous duty; it must never open a phantom new session.
            $nextDayAhead = $schedule['next_day_out_ahead_margin'] ?? null;
            $nextDayAbove = $schedule['next_day_out_above_margin'] ?? null;

            if (($schedule['is_overnight'] ?? false) && $nextDayAhead && $nextDayAbove) {
                $departureDate = CarbonImmutable::parse($scheduleDate)->addDay()->toDateString();
                $departure = $this->schedule($employeeId, $departureDate);

                if (! ($departure['is_work_day'] ?? false)
                    && $this->isWithinWindow($punchAt, $departureDate, $nextDayAhead, $nextDayAbove)) {
                    $matchingTypes[] = 'check_out';
                }
            }
        }

        if ($matchingTypes !== []) {
            // Some legacy schedules have intentionally overlapping windows.
            // An open session makes the same punch a checkout; otherwise it
            // is the employee's first check-in for that duty period.
            $type = $preferCheckOut && in_array('check_out', $matchingTypes, true)
                ? 'check_out'
                : (in_array('check_in', $matchingTypes, true) ? 'check_in' : 'check_out');

            return ['type' => $type, 'has_configured_window' => true];
        }

        return ['type' => null, 'has_configured_window' => $hasConfiguredWindow];
    }

    /**
     * Determine whether a punch is a valid check-in according to its rotation.
     */
    public function isCheckInPunch(int $employeeId, DateTimeInterface $punchAt): bool
    {
        return $this->classify($employeeId, $punchAt)['type'] === 'check_in';
    }

    /**
     * Determine whether the check-in window had started by the report cutoff.
     *
     * A report run at 09:20 must not assess a rotation whose entry window
     * starts at 09:30, even if a later biometric punch already exists in the
     * queried historical data.
     */
    public function hasCheckInWindowStarted(int $employeeId, string $date, string $cutoffTime): bool
    {
        $schedule = $this->schedule($employeeId, $date);
        $windowStart = $schedule['in_ahead_margin'] ?? null;

        if (! ($schedule['is_work_day'] ?? false) || ! $windowStart) {
            return false;
        }

        $cutoff = CarbonImmutable::parse($date.' '.substr($cutoffTime, 0, 8));
        $start = CarbonImmutable::parse($date.' '.substr($windowStart, 0, 8));

        return $cutoff->greaterThanOrEqualTo($start);
    }

    /**
     * Check a punch against an inclusive time window, including overnight ones.
     */
    private function isWithinWindow(CarbonImmutable $punchAt, string $date, string $start, string $end): bool
    {
        $windowStart = CarbonImmutable::parse($date.' '.substr($start, 0, 8));
        $windowEnd = CarbonImmutable::parse($date.' '.substr($end, 0, 8));
        if ($windowEnd->lt($windowStart)) {
            $windowEnd = $windowEnd->addDay();
        }

        return $punchAt->betweenIncluded($windowStart, $windowEnd);
    }

    /**
     * Resolve and cache one employee schedule per roster date.
     *
     * @return array<string, mixed>
     */
    private function schedule(int $employeeId, string $date): array
    {
        $key = $employeeId.'|'.$date;

        return $this->scheduleCache[$key] ??= $this->scheduleResolver->resolve($employeeId, $date);
    }
}
