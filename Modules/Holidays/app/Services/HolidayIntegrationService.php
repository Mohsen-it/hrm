<?php

namespace Modules\Holidays\Services;

use Illuminate\Support\Facades\DB;
use Modules\Holidays\Models\Holiday;

/**
 * HolidayIntegrationService — bridges the Holidays module to the
 * Attendance module.
 *
 * Walks every active holiday whose materialised dates fall in the
 * supplied range and patches the matching `daily_attendance_summaries`
 * rows with `status = 'holiday'`. The patch is idempotent — re-running
 * the job over the same range is a no-op.
 */
class HolidayIntegrationService
{
    /**
     * Apply `holiday` patches for every active holiday in the supplied range.
     *
     * @return array{holidays:int, summaries_patched:int}
     */
    public function syncRange(string $from, string $to): array
    {
        $holidays = $this->holidayService()->getActiveInRange($from, $to);

        $patched = 0;
        foreach ($holidays as $holiday) {
            /** @var Holiday $holiday */
            $dates = $holiday->occurrencesInRange($from, $to);
            foreach ($dates as $date) {
                $patched += $this->patchSummary($date, $holiday);
            }
        }

        return ['holidays' => $holidays->count(), 'summaries_patched' => $patched];
    }

    /**
     * Apply `holiday` patches for a single holiday across an explicit range.
     */
    public function syncHoliday(Holiday $holiday, string $from, string $to): int
    {
        $count = 0;
        foreach ($holiday->occurrencesInRange($from, $to) as $date) {
            $count += $this->patchSummary($date, $holiday);
        }

        return $count;
    }

    /**
     * Patch a single summary row in `daily_attendance_summaries` to mark
     * the supplied date as a holiday.
     *
     * The patch only touches existing summary rows — the `user_id` FK
     * prevents inserting a placeholder row. Summary rows are produced
     * by the daily attendance calculation service; the holiday patch is
     * applied retroactively once a row exists.
     */
    protected function patchSummary(string $date, Holiday $holiday): int
    {
        $now = now();
        $notes = __('holidays.integration.holiday_note', [
            'name' => $holiday->name_ar,
            'id' => $holiday->id,
        ]);

        $count = DB::table('daily_attendance_summaries')
            ->where('summary_date', $date)
            ->update([
                'status' => 'holiday',
                'notes' => $notes,
                'updated_at' => $now,
            ]);

        return $count;
    }

    /**
     * Lazy-resolve the HolidayService so the integration service has no
     * constructor dependencies (it is built by the Holidays provider).
     */
    protected function holidayService(): HolidayService
    {
        return app(HolidayService::class);
    }
}
