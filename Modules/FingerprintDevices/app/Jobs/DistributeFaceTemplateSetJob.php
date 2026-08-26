<?php

namespace Modules\FingerprintDevices\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\FaceTemplateDistributionService;
use Modules\Users\Models\User;

/** Queue a complete face-template enrollment set for all eligible ADMS devices. */
class DistributeFaceTemplateSetJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $backoff = 10;

    public function __construct(
        public int $userId,
        public int $sourceDeviceId,
        public string $sourceSerial,
        public string $setId,
    ) {}

    public function handle(
        FaceTemplateDistributionService $distributionService,
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
            try {
                // Identity is handled by EmployeeAdmsObserver (bridge write —
                // push USERINFO corrupts Arabic names on this firmware).
                // Queue all face templates for this enrollment set.
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
            } catch (\Throwable $e) {
                Log::error('FACE_TEMPLATE_SET_DISTRIBUTION_FAILED', [
                    'user_id' => $user->id,
                    'target_device_id' => $target->id,
                    'set_id' => $this->setId,
                    'error' => $e->getMessage(),
                ]);
                // Re-throw to trigger job retry
                throw $e;
            }
        }
    }
}
