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

        // بصمة الخروج بعد منتصف الليل تُنسب لوردية اليوم السابق (انصراف ليلي)،
        // وإلا ضاعت بين نافذتين: خارج نافذة خروج اليوم السابق، وخارج/داخل
        // نافذة دخول اليوم التالي (خطر احتسابها دخولاً = تأخير وهمي).
        if ($isWorkDay && ($schedule['expected_check_out'] ?? null)) {
            $earlyNextDay = $this->overtimePunchesNextMorning($punches, $date, $schedule, $checkOutPunches->isEmpty());
            if ($earlyNextDay->isNotEmpty()) {
                $checkOutPunches = $checkOutPunches->merge($earlyNextDay);
            }
        }

        $firstCheckInModel = $checkInPunches->sortBy('punch_time')->first();
        $firstCheckIn = $firstCheckInModel?->punch_time;
        $lastCheckOut = $checkOutPunches->sortByDesc('punch_time')->first()?->punch_time;
        $graceMinutes = isset($schedule['grace_minutes']) && $schedule['grace_minutes'] !== null
            ? (int) $schedule['grace_minutes']
            : null;
        $earlyMargin = isset($schedule['early_margin']) && $schedule['early_margin'] !== null
            ? (int) $schedule['early_margin']
            : null;

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
            'is_overnight_checkout' => $lastCheckOut && $lastCheckOut->format('Y-m-d') !== $date->toDateString(),
            'check_in_punches_count' => $checkInPunches->count(),
            'check_out_punches_count' => $checkOutPunches->count(),
            'grace_minutes' => $graceMinutes,
            'early_margin' => $earlyMargin,
            'late_minutes' => $this->computeLateMinutes($date, $schedule, $firstCheckIn),
            'early_leave_minutes' => $this->computeEarlyLeaveMinutes($date, $schedule, $firstCheckInModel, $lastCheckOut, $punches),
        ];
    }

    /**
     * حساب دقائق التأخير حسب سماحية الدخول.
     *
     * القاعدة (حسب طلب العميل):
     * - حد السماحية = المتوقع + grace_minutes (سماحية الدورية/الجدول).
     *   نافذة الدخول الواسعة (مثال 07:00-12:00) تُستخدم فقط لتحديد أي
     *   البصمات تُحتسب دخولاً، وليست حد السماحية.
     * - مثال: متوقع 08:00 وسماحية 30 دقيقة → بصمة 08:25 = صفر،
     *   وبصمة 08:35 = 35 دقيقة (كامل الفرق عن المتوقع وليس ما فوق السماحية).
     *
     * @param  array<string, mixed>  $schedule
     */
    private function computeLateMinutes(CarbonImmutable $date, array $schedule, mixed $actualCheckIn): int
    {
        if (! $actualCheckIn) {
            return 0;
        }

        if (! ($schedule['is_work_day'] ?? false)) {
            return 0;
        }

        $expectedStr = $schedule['expected_check_in'] ?? null;
        if (! $expectedStr) {
            return 0;
        }

        $expected = $this->atDate($date, (string) $expectedStr);
        $actual = CarbonImmutable::parse($actualCheckIn->format('Y-m-d H:i:s'));

        if ($actual->lte($expected)) {
            return 0;
        }

        // حد السماحية: grace أولاً (الحالة الشائعة)، ثم نهاية نافذة
        // الدخول للدوريات التي تُشفّر السماحية في النافذة فقط.
        $grace = (int) ($schedule['grace_minutes'] ?? 0);
        if ($grace > 0) {
            $deadline = $expected->addMinutes($grace);
        } else {
            $windowEndStr = $schedule['in_above_margin'] ?? null;
            $deadline = $windowEndStr
                ? $this->atDate($date, (string) $windowEndStr)
                : $expected;
            if ($deadline->lt($expected)) {
                $deadline = $deadline->addDay();
            }
        }

        if ($actual->lte($deadline)) {
            return 0;
        }

        return $expected->diffInMinutes($actual);
    }

    /**
     * حساب دقائق الخروج المبكر حسب سماحية الخروج (مرآة قاعدة الدخول).
     *
     * - بصمة خروج ضمن السماحية (بعد المتوقع − early_margin) = صفر.
     * - مثال: انصراف متوقع 15:00 وسماحية خروج 30 دقيقة → بصمة 14:45 = صفر،
     *   وبصمة 14:10 = 50 دقيقة (كامل الفرق عن المتوقع).
     * - تُحتسب من بصمة الخروج المعروضة (ضمن نافذة الخروج) أولاً، وإن غابت
     *   يُلتقط انصراف مبكر من بصمات نفس اليوم الواقعة بعد إغلاق نافذة
     *   الدخول وقبل المتوقع (تغطية من يبصم خروجه قبل بداية نافذة الخروج).
     *
     * @param  array<string, mixed>  $schedule
     */
    private function computeEarlyLeaveMinutes(
        CarbonImmutable $date,
        array $schedule,
        mixed $firstCheckInModel,
        mixed $lastCheckOut,
        Collection $punches,
    ): int {
        if (! ($schedule['is_work_day'] ?? false)) {
            return 0;
        }

        if (! $firstCheckInModel) {
            return 0;
        }

        $expectedStr = $schedule['expected_check_out'] ?? null;
        if (! $expectedStr) {
            return 0;
        }

        $expected = $this->atDate($date, (string) $expectedStr);
        $deadline = $this->earlyDeadline($date, $schedule, $expected);

        // القاعدة 1: بصمة خروج ضمن نافذة الخروج (المعروضة في التقرير).
        if ($lastCheckOut) {
            $actual = CarbonImmutable::parse($lastCheckOut->format('Y-m-d H:i:s'));

            return $actual->gte($deadline) ? 0 : $actual->diffInMinutes($expected);
        }

        // القاعدة 2: لا بصمة خروج ضمن النافذة — التقط انصرافاً مبكراً من
        // بصمات نفس اليوم بعد إغلاق نافذة الدخول. نتجاوزها إن وُجد أي حضور
        // بعده (بصمة بنفس اليوم عند المتوقع أو بعده) فتلك حالة نسيان بصمة
        // لا انصراف مبكر. تُستثنى المناوبات الليلية لتداخل الأيام.
        if ((bool) ($schedule['is_overnight'] ?? false)) {
            return 0;
        }

        $dateStr = $date->toDateString();
        $checkInTime = CarbonImmutable::parse($firstCheckInModel->punch_time->format('Y-m-d H:i:s'));
        $zoneStartStr = $schedule['in_above_margin'] ?? null;
        $zoneStart = $zoneStartStr
            ? $this->atDate($date, (string) $zoneStartStr)
            : $this->atDate($date, '12:00');

        $dayPunches = $punches
            ->filter(fn ($punch) => $punch->punch_time->format('Y-m-d') === $dateStr
                && $punch->id !== $firstCheckInModel->id)
            ->map(fn ($punch) => CarbonImmutable::parse($punch->punch_time->format('Y-m-d H:i:s')));

        if ($dayPunches->contains(fn ($time) => $time->gte($expected) && $time->gt($checkInTime))) {
            return 0;
        }

        $candidate = $dayPunches
            ->filter(fn ($time) => $time->gt($checkInTime) && $time->gt($zoneStart) && $time->lt($expected))
            ->sort()
            ->last();

        if (! $candidate) {
            return 0;
        }

        return $candidate->gte($deadline) ? 0 : $candidate->diffInMinutes($expected);
    }

    /**
     * حد سماحية الخروج: المتوقع − early_margin، ثم بداية نافذة الخروج.
     */
    private function earlyDeadline(CarbonImmutable $date, array $schedule, CarbonImmutable $expected): CarbonImmutable
    {
        $earlyMargin = (int) ($schedule['early_margin'] ?? 0);
        if ($earlyMargin > 0) {
            return $expected->subMinutes($earlyMargin);
        }

        $windowStartStr = $schedule['out_ahead_margin'] ?? null;
        if ($windowStartStr) {
            $windowStart = $this->atDate($date, (string) $windowStartStr);
            if ($windowStart->gt($expected)) {
                return $expected;
            }

            return $windowStart;
        }

        return $expected;
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
     * بصمات فجر اليوم التالي تُنسب لوردية اليوم السابق كانصراف ليلي.
     *
     * شريحتان:
     * - 00:00-05:00: انصراف ليلي مؤكد → يُلتقط دائماً (أحدث بصمة تفوز
     *   كآخر خروج، وهو السلوك الحالي المحافظ عليه).
     * - 05:00 حتى بداية نافذة دخول اليوم التالي (07:00 غالباً): تُلتقط فقط
     *   عند غياب أي خروج ضمن نافذة الخروج (إنقاذ حالة نسيان خروج)، وإلا
     *   تُترك لأنها على الأرجح وصول مبكر لليوم التالي. للمناوبات الليلية
     *   يبقى السقف 05:00 (السلوك الحالي دون تغيير).
     *
     * @param  array<string, mixed>  $schedule
     */
    private function overtimePunchesNextMorning(
        Collection $punches,
        CarbonImmutable $date,
        array $schedule,
        bool $prevMissingCheckout,
    ): Collection {
        $nextDay = $date->addDay();
        $nextDayStr = $nextDay->toDateString();
        $expected = $this->atDate($date, (string) $schedule['expected_check_out']);

        $capStr = '05:00';
        if (! ($schedule['is_overnight'] ?? false) && ($schedule['in_ahead_margin'] ?? null)) {
            $capStr = substr((string) $schedule['in_ahead_margin'], 0, 5);
        }
        $cap = CarbonImmutable::parse($nextDayStr.' '.$capStr);

        return $punches->filter(function ($punch) use ($nextDayStr, $cap, $expected, $prevMissingCheckout) {
            $pt = $punch->punch_time;
            if ($pt->format('Y-m-d') !== $nextDayStr) {
                return false;
            }

            // عند سقف الالتقاط أو بعده تبدأ نافذة دخول اليوم التالي.
            if (! $pt->lessThan($cap)) {
                return false;
            }

            // يجب أن تكون بعد الانصراف المتوقع للوردية السابقة.
            if (! $pt->greaterThan($expected)) {
                return false;
            }

            // الشريحة الموسعة (05:00-السقف) تُنقذ فقط اليوم بلا خروج.
            if ($pt->format('H:i') >= '05:00') {
                return $prevMissingCheckout;
            }

            return true;
        });
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
