<?php

namespace Modules\Holidays\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Holidays\Services\HolidayIntegrationService;

/**
 * SyncHolidaysToAttendance — patch `daily_attendance_summaries.status` to
 * `holiday` for every active holiday inside the supplied range.
 *
 * The job is idempotent: re-running it on the same range overwrites the
 * `holiday` patch with the same value, so it is safe to dispatch on
 * every holiday add/edit and as a nightly cron.
 */
class SyncHolidaysToAttendance implements ShouldQueue
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
    ) {}

    /**
     * Execute the job.
     *
     * @return array{holidays:int, summaries_patched:int}
     */
    public function handle(HolidayIntegrationService $service): array
    {
        return $service->syncRange($this->from, $this->to);
    }
}
