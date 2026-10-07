<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix the departure-morning exit window of the 10:00-10:00 overnight
     * schedules (3-9 and 4-12 duties, att_time_schedules#5).
     *
     * out_ahead_margin = 605 placed the window start at 23:55, so the
     * departure punch (~10:00 on the first rest morning) never matched the
     * next-day exit window and dangled as an extra punch while the last duty
     * day stayed open. 60/240 mirrors the entry window (09:00-14:00) and
     * covers the real departure checkout.
     */
    public function up(): void
    {
        DB::table('att_time_schedules')
            ->where('in_time', '10:00:00')
            ->where('out_time', '10:00:00')
            ->where('out_ahead_margin', 605)
            ->update(['out_ahead_margin' => 60, 'out_above_margin' => 240]);
    }

    /**
     * Restore the previous departure window margins.
     */
    public function down(): void
    {
        DB::table('att_time_schedules')
            ->where('in_time', '10:00:00')
            ->where('out_time', '10:00:00')
            ->where('out_ahead_margin', 60)
            ->update(['out_ahead_margin' => 605, 'out_above_margin' => 540]);
    }
};
