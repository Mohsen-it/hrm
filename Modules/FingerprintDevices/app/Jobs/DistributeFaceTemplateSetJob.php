<?php

namespace Modules\FingerprintDevices\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\DeviceCommandService;
use Modules\FingerprintDevices\Services\FaceTemplateDistributionService;
use Modules\Users\Models\User;

/** Queue a complete face-template enrollment set for all eligible ADMS devices. */
class DistributeFaceTemplateSetJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $userId,
        public int $sourceDeviceId,
        public string $sourceSerial,
        public string $setId,
    ) {}

    public function handle(
        FaceTemplateDistributionService $distributionService,
        DeviceCommandService $commandService,
    ): void {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $targets = FingerprintDevice::query()
            ->with('deviceType')
            ->where('is_push_enabled', true)
            ->where('id', '!=', $this->sourceDeviceId)
            ->get()
            ->filter(fn (FingerprintDevice $device) => $device->getDriverName() === 'zkteco');

        foreach ($targets as $target) {
            // ADMS processes lower priorities first: ensure the employee is present before BIODATA.
            $commandService->queueUserUpdate(
                $target->id,
                (string) $user->employee_code,
                (string) ($user->name ?: $user->full_name_ar ?: $user->employee_code),
            );

            $result = $distributionService->queueSetForDevice(
                $target,
                $user->id,
                $this->sourceSerial,
                $this->setId,
            );

            Log::info('FACE_TEMPLATE_SET_AUTO_DISTRIBUTION_QUEUED', [
                'user_id' => $user->id,
                'source_device_id' => $this->sourceDeviceId,
                'target_device_id' => $target->id,
                'set_id' => $this->setId,
                ...$result,
            ]);
        }
    }
}
