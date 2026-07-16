<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Services\DailyAttendanceAutoCalculationService;

/**
 * Attendance:GenerateDailySummaries — alias for the missing-summary back-fill.
 */
class GenerateDailySummariesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:generate-daily-summaries
                            {--from= : Start date (YYYY-MM-DD). Defaults to 1 day ago.}
                            {--to= : End date (YYYY-MM-DD). Defaults to today.}';

    /**
     * The console command description.
     */
    protected $description = 'Generate daily attendance summaries only for missing (user, date) pairs';

    /**
     * Execute the console command.
     */
    public function handle(DailyAttendanceAutoCalculationService $service): int
    {
        $from = (string) ($this->option('from') ?? now()->subDay()->toDateString());
        $to = (string) ($this->option('to') ?? now()->toDateString());

        $this->info("Generating missing summaries from {$from} to {$to}...");

        $count = $service->recalculateMissingForRange($from, $to);

        $this->info("Done. Generated {$count} missing summary rows.");

        return self::SUCCESS;
    }
}
