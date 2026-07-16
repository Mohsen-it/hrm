<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Services\DailyAttendanceAutoCalculationService;

/**
 * Attendance:MarkAbsent — ensure every active employee has a summary row.
 *
 * The auto-calculation service will mark any employee with no sessions on a
 * scheduled day as "absent". Running this command is the canonical way to
 * back-fill absence rows at the end of the day.
 */
class MarkAbsentCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:mark-absent
                            {--date= : Date to mark (YYYY-MM-DD). Defaults to yesterday.}';

    /**
     * The console command description.
     */
    protected $description = 'Mark employees with no sessions on a date as absent';

    /**
     * Execute the console command.
     */
    public function handle(DailyAttendanceAutoCalculationService $service): int
    {
        $date = (string) ($this->option('date') ?? now()->subDay()->toDateString());

        $this->info("Marking absentees for {$date}...");

        $count = $service->calculateForDate($date);

        $this->info("Done. Processed {$count} employees for {$date}.");

        return self::SUCCESS;
    }
}
