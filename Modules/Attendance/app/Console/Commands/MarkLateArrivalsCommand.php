<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Services\AttendanceNotificationService;

/**
 * Attendance:MarkLateArrivals — notify employees who arrived late today.
 */
class MarkLateArrivalsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:mark-late-arrivals
                            {--date= : Date to scan (YYYY-MM-DD). Defaults to today.}
                            {--threshold=1 : Notify when late minutes >= threshold}';

    /**
     * The console command description.
     */
    protected $description = 'Notify employees flagged as late on a given date';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceNotificationService $notifications): int
    {
        $date = (string) ($this->option('date') ?? now()->toDateString());
        $threshold = (int) $this->option('threshold');

        $this->info("Scanning late arrivals for {$date} (threshold={$threshold}m)...");

        // Reuse the daily scan which already iterates summaries and notifies.
        $result = $notifications->runDailyScan($date);

        $this->info('Done. '.json_encode($result, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
