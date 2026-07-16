<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Services\DailyAttendanceAutoCalculationService;

/**
 * Attendance:RecalculateRange — rebuild summaries for an inclusive date range.
 */
class RecalculateRangeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:recalculate-range
                            {--from= : Start date (YYYY-MM-DD). Defaults to 7 days ago.}
                            {--to= : End date (YYYY-MM-DD). Defaults to today.}';

    /**
     * The console command description.
     */
    protected $description = 'Rebuild daily attendance summaries for a calendar range';

    /**
     * Execute the console command.
     */
    public function handle(DailyAttendanceAutoCalculationService $service): int
    {
        $from = (string) ($this->option('from') ?? now()->subDays(7)->toDateString());
        $to = (string) ($this->option('to') ?? now()->toDateString());

        $this->info("Recalculating daily summaries from {$from} to {$to}...");

        $count = $service->calculateForDateRange($from, $to);

        $this->info("Done. Rebuilt {$count} rows.");

        return self::SUCCESS;
    }
}
