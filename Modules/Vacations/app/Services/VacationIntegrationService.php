<?php

namespace Modules\Vacations\Services;

use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Modules\Vacations\Models\UserVacationRequest;

/**
 * VacationIntegrationService — bridges the Vacations module to the
 * Attendance module.
 *
 * When a vacation request is approved, every calendar day in the
 * request range is marked as `vacation` on the corresponding
 * `daily_attendance_summaries` row (creating the summary on first use
 * if needed). When the request is cancelled, the listener asks the
 * service to revert the patch.
 *
 * The service intentionally touches the `daily_attendance_summaries`
 * table directly — calling `DailyAttendanceAutoCalculationService`
 * here would loop back into the same module and risk double-locking
 * the row.
 */
class VacationIntegrationService
{
    /**
     * Apply a `vacation` patch to every summary row in the request range.
     */
    public function markDatesAsVacation(UserVacationRequest $request): int
    {
        $from = $request->start_date->format('Y-m-d');
        $to = $request->end_date->format('Y-m-d');
        $userId = (int) $request->user_id;
        $typeCode = (string) ($request->vacationType?->code ?? 'vacation');
        $notes = __('vacations.integration.vacation_note', [
            'type' => $typeCode,
            'id' => $request->id,
        ]);

        $count = 0;
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $this->patchSummary(
                userId: $userId,
                date: $day->format('Y-m-d'),
                status: 'vacation',
                notes: $notes,
            );
            $count++;
        }

        return $count;
    }

    /**
     * Revert the `vacation` patch by setting the summary status back to
     * `absent` (so a recalculation re-evaluates it on the next pass).
     *
     * Summaries that already have a real status (e.g. `holiday`) are
     * left untouched.
     */
    public function markDatesBackToDefault(UserVacationRequest $request): int
    {
        $from = $request->start_date->format('Y-m-d');
        $to = $request->end_date->format('Y-m-d');
        $userId = (int) $request->user_id;

        $count = 0;
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $this->patchSummary(
                userId: $userId,
                date: $day->format('Y-m-d'),
                status: 'absent',
                notes: null,
                onlyWhenStatusIs: 'vacation',
            );
            $count++;
        }

        return $count;
    }

    /**
     * Apply a one-row patch to `daily_attendance_summaries`.
     *
     * @param  string|null  $onlyWhenStatusIs  When set, only patches rows whose current status matches.
     */
    protected function patchSummary(int $userId, string $date, string $status, ?string $notes, ?string $onlyWhenStatusIs = null): void
    {
        $now = now();

        $existing = DB::table('daily_attendance_summaries')
            ->where('user_id', $userId)
            ->where('summary_date', $date)
            ->first();

        $payload = [
            'user_id' => $userId,
            'summary_date' => $date,
            'status' => $status,
            'notes' => $notes,
            'updated_at' => $now,
        ];

        if (! $existing) {
            $payload['created_at'] = $now;
            $payload['sessions_count'] = 0;
            $payload['is_complete'] = false;
            $payload['calculated_at'] = $now;

            DB::table('daily_attendance_summaries')->insert($payload);

            return;
        }

        if ($onlyWhenStatusIs !== null && $existing->status !== $onlyWhenStatusIs) {
            return;
        }

        DB::table('daily_attendance_summaries')
            ->where('user_id', $userId)
            ->where('summary_date', $date)
            ->update([
                'status' => $status,
                'notes' => $notes,
                'updated_at' => $now,
            ]);
    }
}
