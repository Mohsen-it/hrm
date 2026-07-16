<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Services\DailyAttendanceAutoCalculationService;

/**
 * Attendance:RecalculateUser — rebuild the daily summary for one employee.
 */
class RecalculateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:recalculate-user
                            {userId : The internal user id}
                            {--from= : Start date (YYYY-MM-DD). Defaults to 30 days ago.}
                            {--to= : End date (YYYY-MM-DD). Defaults to today.}';

    /**
     * The console command description.
     */
    protected $description = 'Rebuild daily attendance summaries for one user across a range';

    /**
     * Execute the console command.
     */
    public function handle(DailyAttendanceAutoCalculationService $service): int
    {
        $userId = (int) $this->argument('userId');
        $from = (string) ($this->option('from') ?? now()->subDays(30)->toDateString());
        $to = (string) ($this->option('to') ?? now()->toDateString());

        $this->info("Recalculating summaries for user {$userId} from {$from} to {$to}...");

        $count = $service->calculateForUserAndRange($userId, $from, $to);

        $this->info("Done. Rebuilt {$count} rows for user {$userId}.");

        return self::SUCCESS;
    }
}
