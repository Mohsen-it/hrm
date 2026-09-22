<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tune punch windows to the observed biometric reality (14-day audit,
     * Sep 2026) so every legitimate punch is classified instead of silently
     * falling into Extra:
     *
     * - TS#5 (3-9 + 4-12, 10:00-10:00 overnight): block day-1 starts ~10:00
     *   but mid/last days punch 06:00-08:00 and departures scatter 06:00-11:00.
     *   Entry 09:00-14:00  → 05:30-14:00 (in_ahead 60 → 270);
     *   departure 09:00-14:00 → 06:00-14:00 (out_ahead 60 → 240).
     * - ROT#15 (4-12) legacy exit 08:00-21:30 overlaps the whole morning: any
     *   morning punch with an open session healed the previous day instead of
     *   opening the new one → 17:00-23:59 (evenings only).
     * - ROT#16 (مراسم, no schedule, exit 07:30-09:30 mornings only): arrivals
     *   spread 08:00-21:00 with an admin-like 08:00-15:00 core → link the
     *   admin schedule (TS#1) and widen the exit to 14:30-18:00.
     * - ROT#22 (آليات, 7-day overnight blocks): schedule-only exit 07:00-12:00
     *   can never close a mid-block evening session → legacy evening exit
     *   17:00-23:59 (mid-block days close; last day stays extra by design and
     *   the departure window keeps 07:00-12:00).
     * - ROT#5 exit 14:30-18:00 → 14:30-20:00 (19:xx overtime checkouts).
     * - ROT#9 exit 14:30-16:00 → 14:30-17:00 (16:xx checkouts).
     *
     * Untouched (verified against the same audit): ROT#1, ROT#2, ROT#4,
     * ROT#6, ROT#14 — entry/exit/departure windows already cover every
     * observed punch cluster.
     */
    public function up(): void
    {
        DB::table('att_time_schedules')
            ->where('in_time', '10:00:00')
            ->where('out_time', '10:00:00')
            ->where('is_multi_day', true)
            ->update(['in_ahead_margin' => 270, 'out_ahead_margin' => 240]);

        DB::table('att_rotations')->where('id', 15)->update([
            'out_ahead_margin' => '17:00:00', 'out_above_margin' => '23:59:00',
        ]);

        $adminScheduleId = DB::table('att_time_schedules')
            ->where('in_time', '08:00:00')
            ->where('out_time', '15:00:00')
            ->where('is_multi_day', false)
            ->orderBy('id')
            ->value('id');

        DB::table('att_rotations')->where('id', 16)->update([
            'time_schedule_id' => $adminScheduleId,
            'out_ahead_margin' => '14:30:00', 'out_above_margin' => '18:00:00',
        ]);

        DB::table('att_rotations')->where('id', 22)->update([
            'out_ahead_margin' => '17:00:00', 'out_above_margin' => '23:59:00',
        ]);

        DB::table('att_rotations')->where('id', 5)->update([
            'out_above_margin' => '20:00:00',
        ]);

        DB::table('att_rotations')->where('id', 9)->update([
            'out_above_margin' => '17:00:00',
        ]);
    }

    /**
     * Restore the previous window values.
     */
    public function down(): void
    {
        DB::table('att_time_schedules')
            ->where('in_time', '10:00:00')
            ->where('out_time', '10:00:00')
            ->where('is_multi_day', true)
            ->update(['in_ahead_margin' => 60, 'out_ahead_margin' => 60]);

        DB::table('att_rotations')->where('id', 15)->update([
            'out_ahead_margin' => '08:00:00', 'out_above_margin' => '21:30:00',
        ]);

        DB::table('att_rotations')->where('id', 16)->update([
            'time_schedule_id' => null,
            'out_ahead_margin' => '07:30:00', 'out_above_margin' => '09:30:00',
        ]);

        DB::table('att_rotations')->where('id', 22)->update([
            'out_ahead_margin' => null, 'out_above_margin' => null,
        ]);

        DB::table('att_rotations')->where('id', 5)->update([
            'out_above_margin' => '18:00:00',
        ]);

        DB::table('att_rotations')->where('id', 9)->update([
            'out_above_margin' => '16:00:00',
        ]);
    }
};
