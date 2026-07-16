<?php

namespace Modules\Attendance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Services\DailyAttendanceAutoCalculationService;

/**
 * RecalculateDateRangeChunk — recompute the daily summary for every active
 * employee in the supplied inclusive date range.
 *
 * The work is delegated to `DailyAttendanceAutoCalculationService`, which
 * already chunks the user set and the date range to avoid unbounded memory
 * usage. The job is the queue-friendly entry point used by the cron and
 * by the operator-triggered "Rebuild Range" button.
 */
class RecalculateDateRangeChunk implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of seconds the job may run before timing out.
     */
    public int $timeout = 1800;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $from,
        public string $to,
        public bool $missingOnly = false,
    ) {}

    /**
     * Execute the job.
     *
     * @return int Number of (user, date) pairs rebuilt
     */
    public function handle(DailyAttendanceAutoCalculationService $service): int
    {
        if ($this->missingOnly) {
            return $service->recalculateMissingForRange($this->from, $this->to);
        }

        return $service->calculateForDateRange($this->from, $this->to);
    }
}
