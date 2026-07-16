<?php

namespace Modules\Zones\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\Zones\Models\Zone;

/**
 * SyncZoneDevices — refreshes the cached `devices_count` column for
 * the supplied zone.
 *
 * The job iterates over the `zone_branches` pivot, counts the active
 * fingerprint devices on each branch, and persists the total. The
 * job is intentionally idempotent: re-running it is safe.
 */
class SyncZoneDevices implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(
        public int $zoneId,
    ) {}

    public function handle(): void
    {
        $zone = Zone::whereKey($this->zoneId)->first();
        if (! $zone) {
            Log::warning('SyncZoneDevices: zone not found', ['zone_id' => $this->zoneId]);

            return;
        }

        $count = (int) FingerprintDevice::query()
            ->join('zone_branches as zb', 'zb.branch_id', '=', 'fingerprint_devices.branch_id')
            ->where('zb.zone_id', $this->zoneId)
            ->whereNull('fingerprint_devices.deleted_at')
            ->count();

        DB::table('zones')
            ->where('id', $this->zoneId)
            ->update([
                'devices_count' => $count,
                'updated_at' => now(),
            ]);

        Log::info('SyncZoneDevices: complete', [
            'zone_id' => $this->zoneId,
            'devices_count' => $count,
        ]);
    }
}
