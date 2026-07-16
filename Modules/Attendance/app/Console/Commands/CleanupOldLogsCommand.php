<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Models\RawAttendanceLog;

/**
 * Attendance:CleanupOldLogs — soft-delete raw logs older than N days.
 *
 * Raw logs are only useful while they are still being correlated into
 * sessions. Once a daily summary is finalised, the underlying raw log can
 * be pruned to keep the table small.
 */
class CleanupOldLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:cleanup-old-logs
                            {--days=180 : Keep raw logs for the last N days}
                            {--chunk=500 : Chunk size for the deletion loop}
                            {--force : Actually delete (default: dry-run)}';

    /**
     * The console command description.
     */
    protected $description = 'Soft-delete raw attendance logs older than the configured retention window';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $chunk = (int) $this->option('chunk');
        $force = (bool) $this->option('force');

        $cutoff = now()->subDays($days);
        $this->info("Cleaning raw logs older than {$cutoff->toDateTimeString()} (force=".($force ? 'yes' : 'no').')...');

        $count = 0;
        RawAttendanceLog::query()
            ->where('created_at', '<', $cutoff)
            ->chunkById($chunk, function ($logs) use (&$count, $force): void {
                foreach ($logs as $log) {
                    if ($force) {
                        $log->delete();
                    }
                    $count++;
                }
            });

        $this->info('Done. '.($force ? 'Deleted' : 'Would delete')." {$count} rows.");

        return self::SUCCESS;
    }
}
