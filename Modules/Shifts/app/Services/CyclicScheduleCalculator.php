<?php

namespace Modules\Shifts\Services;

use Carbon\Carbon;

class CyclicScheduleCalculator
{
    /**
     * Determine if a given date is a work day in a cyclic schedule.
     *
     * Formula: ($date->diffInDays($cycleStart) % ($workDays + $restDays)) < $workDays
     */
    public function isWorkDay(Carbon $date, Carbon $cycleStart, int $workDays, int $restDays): bool
    {
        $dayInCycle = $date->diffInDays($cycleStart) % ($workDays + $restDays);

        return $dayInCycle < $workDays;
    }

    /**
     * The 1-based day index inside the cycle for the given target date.
     *
     * Core mapping equation (derived, no loops):
     *   Day_Index = ((Target_Date - Start_Date) % Cycle_Length) + 1
     *
     * Returns null when the cycle length is non-positive (undefined schedule).
     */
    public function dayIndex(Carbon $targetDate, Carbon $startDate, int $cycleLength): ?int
    {
        if ($cycleLength <= 0) {
            return null;
        }

        $offset = $targetDate->startOfDay()->diffInDays($startDate->startOfDay());

        return ($offset % $cycleLength) + 1;
    }

    /**
     * Get all work days in a given month for a cyclic schedule.
     *
     * @return array<int, Carbon>
     */
    public function getWorkDays(int $month, int $year, Carbon $cycleStart, int $workDays, int $restDays): array
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $workDaysList = [];
        $current = $startOfMonth->copy();

        while ($current->lte($endOfMonth)) {
            if ($this->isWorkDay($current, $cycleStart, $workDays, $restDays)) {
                $workDaysList[] = $current->copy();
            }
            $current->addDay();
        }

        return $workDaysList;
    }

    /**
     * Get the next work day relative to the given date.
     */
    public function getNextWorkDay(Carbon $date, Carbon $cycleStart, int $workDays, int $restDays): Carbon
    {
        $next = $date->copy()->addDay();

        while (! $this->isWorkDay($next, $cycleStart, $workDays, $restDays)) {
            $next->addDay();
        }

        return $next;
    }

    /**
     * Get the next rest day relative to the given date.
     */
    public function getNextRestDay(Carbon $date, Carbon $cycleStart, int $workDays, int $restDays): Carbon
    {
        $next = $date->copy()->addDay();

        while ($this->isWorkDay($next, $cycleStart, $workDays, $restDays)) {
            $next->addDay();
        }

        return $next;
    }

    /**
     * Get work blocks (continuous ranges of work days) between two dates.
     *
     * @return array<int, array{start_date: Carbon, end_date: Carbon}>
     */
    public function getWorkBlocks(Carbon $cycleStart, int $workDays, int $restDays, Carbon $fromDate, Carbon $toDate): array
    {
        $blocks = [];
        $current = $fromDate->copy()->startOfDay();
        $blockStart = null;

        while ($current->lte($toDate)) {
            if ($this->isWorkDay($current, $cycleStart, $workDays, $restDays)) {
                if ($blockStart === null) {
                    $blockStart = $current->copy();
                }
            } else {
                if ($blockStart !== null) {
                    $blocks[] = [
                        'start_date' => $blockStart,
                        'end_date' => $current->copy()->subDay(),
                    ];
                    $blockStart = null;
                }
            }
            $current->addDay();
        }

        if ($blockStart !== null) {
            $blocks[] = [
                'start_date' => $blockStart,
                'end_date' => $toDate->copy(),
            ];
        }

        return $blocks;
    }

    /**
     * Get all work days in a given date range for a cyclic schedule.
     *
     * @return array<int, array{date: string, is_work_day: bool}>
     */
    public function getScheduleInRange(Carbon $cycleStart, int $workDays, int $restDays, Carbon $fromDate, Carbon $toDate): array
    {
        $schedule = [];
        $current = $fromDate->copy()->startOfDay();

        while ($current->lte($toDate)) {
            $isWorkDay = $this->isWorkDay($current, $cycleStart, $workDays, $restDays);
            $schedule[] = [
                'date' => $current->format('Y-m-d'),
                'is_work_day' => $isWorkDay,
            ];
            $current->addDay();
        }

        return $schedule;
    }
}
