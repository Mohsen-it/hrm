<?php

namespace Modules\Attendance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Attendance\Services\DailyAttendanceSummaryService;

/**
 * RecalculateDailySummariesChunk — recompute the daily summary for a chunk
 * of (user, date) pairs.
 *
 * The job is dispatched by the daily-summaries controller when the operator
 * asks for a back-fill or a recalculation. The chunk size is driven by
 * `config('attendance.chunk_size')`.
 */
class RecalculateDailySummariesChunk implements ShouldQueue
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
     *
     * @param  array<int, array{user_id: int, date: string}>  $pairs
     */
    public function __construct(
        public array $pairs = [],
    ) {}

    /**
     * Execute the job.
     *
     * @return int Number of (user, date) pairs that were rebuilt
     */
    public function handle(DailyAttendanceSummaryService $service): int
    {
        $count = 0;
        foreach ($this->pairs as $pair) {
            $userId = (int) ($pair['user_id'] ?? 0);
            $date = (string) ($pair['date'] ?? '');

            if ($userId <= 0 || $date === '') {
                continue;
            }

            $service->recalculateForUserAndDate($userId, $date);
            $count++;
        }

        return $count;
    }
}
