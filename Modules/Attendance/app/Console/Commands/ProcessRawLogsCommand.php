<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Services\RawAttendanceLogService;

/**
 * Attendance:ProcessRawLogs — turn every queued raw log into a session.
 */
class ProcessRawLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:process-raw-logs
                            {--limit=0 : Process at most N logs (0 = no limit)}
                            {--chunk=200 : Chunk size for the iteration}';

    /**
     * The console command description.
     */
    protected $description = 'Convert unprocessed raw attendance logs into attendance sessions';

    /**
     * Execute the console command.
     */
    public function handle(RawAttendanceLogService $service): int
    {
        $limit = (int) $this->option('limit');
        $chunk = (int) $this->option('chunk');

        $this->info("Processing unprocessed raw logs (limit={$limit}, chunk={$chunk})...");

        $result = $service->processAllUnprocessed($chunk);

        $this->info("Processed {$result['processed']} logs, generated {$result['sessions']} sessions.");

        return self::SUCCESS;
    }
}
