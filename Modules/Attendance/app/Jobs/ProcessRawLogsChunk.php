<?php

namespace Modules\Attendance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Services\RawAttendanceLogService;

/**
 * ProcessRawLogsChunk — correlate a chunk of raw device logs into sessions.
 *
 * The job is dispatched by the raw-logs controller after a bulk import
 * (and by the daily cron). The actual correlation logic lives in
 * `RawAttendanceLogService::processAllUnprocessed`, so the job is a thin
 * queue-friendly wrapper.
 */
class ProcessRawLogsChunk implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of seconds the job may run before timing out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $chunkSize = 200,
    ) {}

    /**
     * Execute the job.
     *
     * @return array{processed: int, sessions: int}
     */
    public function handle(RawAttendanceLogService $service): array
    {
        return $service->processAllUnprocessed($this->chunkSize);
    }
}
