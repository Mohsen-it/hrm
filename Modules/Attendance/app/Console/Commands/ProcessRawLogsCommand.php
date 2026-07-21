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
                            {--chunk=200 : Chunk size for each iteration}
                            {--once : Process a single chunk and exit}';

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
        $once = (bool) $this->option('once');

        $this->info("Processing unprocessed raw logs (limit={$limit}, chunk={$chunk})...");

        $totalProcessed = 0;
        $totalSessions = 0;
        $remainingLimit = $limit > 0 ? $limit : null;

        do {
            $currentChunk = $remainingLimit !== null
                ? min($chunk, $remainingLimit)
                : $chunk;

            $result = $service->processAllUnprocessed($currentChunk);

            $totalProcessed += $result['processed'];
            $totalSessions += $result['sessions'];

            if ($remainingLimit !== null) {
                $remainingLimit -= $result['processed'];
            }

            if ($once || $result['processed'] === 0) {
                break;
            }
        } while ($remainingLimit === null || $remainingLimit > 0);

        $this->info("Processed {$totalProcessed} logs, generated {$totalSessions} sessions.");

        return self::SUCCESS;
    }
}
