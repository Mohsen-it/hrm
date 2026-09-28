<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The time schedule owns lateness grace (single source of truth):
     *
     * - TS "دورية 3-9" (10:00-10:00 overnight): late_margin 30 → 50, parity
     *   with "دورية 7-21". Arrivals 10:31-10:50 were flagged late by 1-20
     *   minutes although the duty pattern allows them (same complaint as
     *   7-21, whose schedule already carries 50).
     * - att_rotations.grace_minutes → 0 wherever a time schedule is linked:
     *   the rotation-level value no longer drives any calculation
     *   (ScheduleResolverService / AbsenceCalculationService read the
     *   schedule's late_margin first). Zeroing removes the stale override
     *   that capped 7-21 at 30 while its schedule allows 50. Rotations
     *   without a schedule keep their own grace as the fallback.
     */
    public function up(): void
    {
        DB::table('att_time_schedules')
            ->where('name', 'دورية 3-9')
            ->update(['late_margin' => 50]);

        DB::table('att_rotations')
            ->whereNotNull('time_schedule_id')
            ->where('grace_minutes', '>', 0)
            ->update(['grace_minutes' => 0]);
    }

    /**
     * Restore the previous grace values (only the four rotations that
     * carried 30 before up(); every other linked rotation already had 0).
     */
    public function down(): void
    {
        DB::table('att_time_schedules')
            ->where('name', 'دورية 3-9')
            ->update(['late_margin' => 30]);

        DB::table('att_rotations')
            ->whereIn('name', [
                'طيران مدني دورية 1-3',
                'طيران مدني دورية 3-9',
                'طيران مدني دورية 7-21',
                'الخطوط السورية دورية 1-3',
            ])
            ->update(['grace_minutes' => 30]);
    }
};
