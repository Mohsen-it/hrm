<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Services\DailyAttendanceAutoCalculationService;

/**
 * Attendance:RecalculateDate — rebuild every summary for one calendar date.
 */
class RecalculateDateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:recalculate-date
                            {date : Date to rebuild (YYYY-MM-DD)}';

    /**
     * The console command description.
     */
    protected $description = 'Rebuild the daily attendance summary for one calendar date';

    /**
     * Execute the console command.
     */
    public function handle(DailyAttendanceAutoCalculationService $service): int
    {
        $date = (string) $this->argument('date');

        $this->info("Recalculating summaries for {$date}...");

        $count = $service->calculateForDate($date);

        $this->info("Done. Rebuilt {$count} rows.");

        return self::SUCCESS;
    }
}
