<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Services\DailyAttendanceAutoCalculationService;

/**
 * Attendance:RecalculateSummaries — rebuild every missing daily summary.
 *
 * Iterates over an inclusive date range and rebuilds the daily roll-up for
 * every active employee. Heavy workloads should be dispatched onto a queue
 * (the constitution flags any operation that may exceed 2 seconds).
 */
class RecalculateSummariesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:recalculate-summaries
                            {--from= : Start date (YYYY-MM-DD). Defaults to 30 days ago.}
                            {--to= : End date (YYYY-MM-DD). Defaults to today.}
                            {--chunk=200 : Chunk size for user iteration}';

    /**
     * The console command description.
     */
    protected $description = 'Rebuild daily attendance summaries for an inclusive date range';

    /**
     * Execute the console command.
     */
    public function handle(DailyAttendanceAutoCalculationService $service): int
    {
        $from = (string) ($this->option('from') ?? now()->subDays(30)->toDateString());
        $to = (string) ($this->option('to') ?? now()->toDateString());
        $chunk = (int) $this->option('chunk');

        $this->info("Recalculating daily summaries from {$from} to {$to} (chunk={$chunk})...");

        $count = $service->calculateForDateRange($from, $to);

        $this->info("Done. Rebuilt {$count} (user, date) pairs.");

        return self::SUCCESS;
    }
}
