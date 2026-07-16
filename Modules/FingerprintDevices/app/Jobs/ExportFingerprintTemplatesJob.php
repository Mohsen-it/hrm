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
 * ExportFingerprintTemplatesJob — exports fingerprint templates from a device.
 */
class ExportFingerprintTemplatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(
        protected int $deviceId,
    ) {}

    public function handle(FingerprintDeviceService $service): void
    {
        $device = FingerprintDevice::find($this->deviceId);

        if (! $device) {
            Log::warning('ExportFingerprintTemplatesJob: device not found', ['id' => $this->deviceId]);

            return;
        }

        Log::info('ExportFingerprintTemplatesJob: starting export', [
            'device_id' => $this->deviceId,
            'device_name' => $device->name,
        ]);

        $service->syncUsers($device);

        Log::info('ExportFingerprintTemplatesJob: export complete', [
            'device_id' => $this->deviceId,
        ]);
    }
}
