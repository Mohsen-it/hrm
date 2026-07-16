<?php

namespace Modules\FingerprintDevices\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;

/**
 * ImportAllFingerprintTemplatesJob — imports all fingerprints from a device.
 */
class ImportAllFingerprintTemplatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        protected int $deviceId,
    ) {}

    public function handle(FingerprintDeviceService $service): void
    {
        $device = FingerprintDevice::find($this->deviceId);

        if (! $device) {
            Log::warning('ImportAllFingerprintTemplatesJob: device not found', ['id' => $this->deviceId]);

            return;
        }

        Log::info('ImportAllFingerprintTemplatesJob: starting import', [
            'device_id' => $this->deviceId,
            'device_name' => $device->name,
        ]);

        $service->syncAttendance($device);

        Log::info('ImportAllFingerprintTemplatesJob: import complete', [
            'device_id' => $this->deviceId,
        ]);
    }
}
