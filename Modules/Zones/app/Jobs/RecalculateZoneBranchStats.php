<?php

namespace Modules\Zones\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Zones\Models\Zone;
use Modules\Zones\Services\ZoneService;

/**
 * RecalculateZoneBranchStats — recomputes the cached counters on
 * `zones` (branches_count, employees_count, devices_count) for a
 * single zone.
 */
class RecalculateZoneBranchStats implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(
        public int $zoneId,
    ) {}

    public function handle(ZoneService $service): void
    {
        if (! Zone::whereKey($this->zoneId)->exists()) {
            Log::warning('RecalculateZoneBranchStats: zone not found', ['zone_id' => $this->zoneId]);

            return;
        }

        $service->refreshCounts($this->zoneId);

        Log::info('RecalculateZoneBranchStats: complete', ['zone_id' => $this->zoneId]);
    }
}
