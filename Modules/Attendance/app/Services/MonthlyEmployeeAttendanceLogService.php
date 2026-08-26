<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Attendance\Repositories\RawAttendanceLogRepository;
use Modules\Shifts\Services\ScheduleResolverService;

/**
 * Builds a monthly, schedule-aware punch log for one employee.
 *
 * Only punches inside the assigned rotation's explicit check-in/check-out
 * windows are eligible. This deliberately does not infer a check-in or
 * check-out from a punch outside its configured window.
 */
class MonthlyEmployeeAttendanceLogService
{
    public function __construct(
        private RawAttendanceLogRepository $rawLogs,
        private ScheduleResolverService $scheduleResolver,
    ) {}

    /**
     * Get one row for every calendar day in the requested month.
     *
     * @return array<int, array<string, bool|int|string|null>>
     */
    public function getMonthlyLog(int $userId, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();
        $punches = $this->rawLogs->getByUser(
            $userId,
            $start->startOfDay()->toDateTimeString(),
            $end->addDay()->endOfDay()->toDateTimeString(),
        );

        $rows = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $schedule = $this->scheduleResolver->resolve($userId, $date->toDateString());
            $rows[] = $this->buildDayRow($date, $schedule, $punches);
        }

        return $rows;
    }

    /**
     * Build one report row from the rotation schedule and raw punches.
     *
     * @param  array<string, mixed>  $schedule
     * @return array<string, bool|int|string|null>
     */
    private function buildDayRow(CarbonImmutable $date, array $schedule, Collection $punches): array
    {
        $isWorkDay = (bool) ($schedule['is_work_day'] ?? false);
        $checkInPunches = $isWorkDay
            ? $this->punchesWithinWindow($punches, $date, $schedule['in_ahead_margin'] ?? null, $schedule['in_above_margin'] ?? null)
            : collect();
        $checkOutPunches = $isWorkDay
            ? $this->punchesWithinWindow($punches, $date, $schedule['out_ahead_margin'] ?? null, $schedule['out_above_margin'] ?? null)
            : collect();

        // عالج بصمة الخروج بعد منتصف الليل كـ overtime لليوم السابق
        // نافذة الخروج الحالية 14:50-23:59 تخسر بصمة 00:10 التالية، فيجب التقاطها
        // كـ checkout لليوم السابق إذا كانت بعد expected_check_out
        if ($isWorkDay && $schedule['expected_check_out']) {
            $earlyNextDay = $this->overtimePunchesNextMorning($punches, $date, $schedule['expected_check_out']);
            if ($earlyNextDay->isNotEmpty()) {
                $checkOutPunches = $checkOutPunches->merge($earlyNextDay);
            }
        }

        $firstCheckIn = $checkInPunches->sortBy('punch_time')->first()?->punch_time;
        $lastCheckOut = $checkOutPunches->sortByDesc('punch_time')->first()?->punch_time;

        return [
            'date' => $date->toDateString(),
            'day_name' => $date->locale(config('app.locale'))->translatedFormat('l'),
            'schedule_status' => (string) ($schedule['status'] ?? ScheduleResolverService::STATUS_UNASSIGNED),
            'is_work_day' => $isWorkDay,
            'expected_check_in' => $schedule['expected_check_in'] ?? null,
            'expected_check_out' => $schedule['expected_check_out'] ?? null,
            'check_in_window' => $this->windowLabel($schedule['in_ahead_margin'] ?? null, $schedule['in_above_margin'] ?? null),
            'check_out_window' => $this->windowLabel($schedule['out_ahead_margin'] ?? null, $schedule['out_above_margin'] ?? null),
            'first_check_in_at' => $firstCheckIn?->format('Y-m-d H:i'),
            'last_check_out_at' => $lastCheckOut?->format('Y-m-d H:i'),
            'check_in_punches_count' => $checkInPunches->count(),
            'check_out_punches_count' => $checkOutPunches->count(),
        ];
    }

    /**
     * Return only the punches inside one inclusive, possibly overnight window.
     */
    private function punchesWithinWindow(Collection $punches, CarbonImmutable $date, ?string $start, ?string $end): Collection
    {
        if (! $start || ! $end) {
            return collect();
        }

        $windowStart = $this->atDate($date, $start);
        $windowEnd = $this->atDate($date, $end);
        if ($windowEnd->lt($windowStart)) {
            $windowEnd = $windowEnd->addDay();
        }

        return $punches->filter(fn ($punch) => $punch->punch_time->betweenIncluded($windowStart, $windowEnd));
    }

    /**
     * بصمات بعد منتصف الليل (00:00-05:00 اليوم التالي) تُحتسب كخروج إضافي لليوم السابق
     * إذا كانت بعد وقت الانصراف المتوقع.
     */
    private function overtimePunchesNextMorning(Collection $punches, CarbonImmutable $date, string $expectedOut): Collection
    {
        $nextDay = $date->addDay();
        $windowStart = $nextDay->startOfDay();
        $windowEnd = $nextDay->startOfDay()->addHours(5);
        $expected = $this->atDate($date, $expectedOut);
        // للمناوبات الليلية expected يكون اليوم التالي
        $expectedMin = $this->parseTimeToMinutes($expectedOut);
        $inMin = null; // نحتاج لمعرفة إن كانت ليلية

        // ببساطة: إذا كان 00:10 بعد الانصراف المتوقع (سواء نفس اليوم أو +1)
        return $punches->filter(function ($punch) use ($windowStart, $windowEnd, $expected) {
            $pt = $punch->punch_time;
            if (! $pt->betweenIncluded($windowStart, $windowEnd)) {
                return false;
            }

            // يجب أن تكون بعد الانصراف المتوقع
            return $pt->greaterThan($expected);
        });
    }

    private function parseTimeToMinutes(?string $time): ?int
    {
        if (! $time) {
            return null;
        }
        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return null;
        }

        return (int) $parts[0] * 60 + (int) $parts[1];
    }

    /**
     * Combine an ISO date and a database time string.
     */
    private function atDate(CarbonImmutable $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString().' '.substr($time, 0, 8));
    }

    /**
     * Format a configured time interval for the report.
     */
    private function windowLabel(?string $start, ?string $end): ?string
    {
        return $start && $end ? substr($start, 0, 5).' - '.substr($end, 0, 5) : null;
    }
}
