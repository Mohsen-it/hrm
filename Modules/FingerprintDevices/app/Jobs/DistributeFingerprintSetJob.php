<?php

namespace Modules\FingerprintDevices\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\FingerprintTemplateDistributionService;
use Modules\Users\Models\User;

/** Queue a freshly enrolled fingerprint for all eligible ADMS devices. */
class DistributeFingerprintSetJob implements ShouldQueue
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
        public ?int $dispatchedAt = null,
    ) {
        $this->dispatchedAt ??= time();
    }

    public function handle(
        FingerprintTemplateDistributionService $distributionService,
    ): void {
        if ($this->distributionHalted()) {
            return;
        }

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
                // Identity is handled by EmployeeAdmsObserver (ADMS USERINFO).
                // Queue the freshest fingerprint templates per finger index.
                $result = $distributionService->queueForDevice(
                    $target,
                    [$user->id],
                );

                Log::info('FINGERPRINT_TEMPLATE_AUTO_DISTRIBUTION_QUEUED', [
                    'user_id' => $user->id,
                    'source_device_id' => $this->sourceDeviceId,
                    'target_device_id' => $target->id,
                    ...$result,
                ]);
            } catch (\Throwable $e) {
                Log::error('FINGERPRINT_TEMPLATE_DISTRIBUTION_FAILED', [
                    'user_id' => $user->id,
                    'target_device_id' => $target->id,
                    'error' => $e->getMessage(),
                ]);
                // Re-throw to trigger job retry
                throw $e;
            }
        }
    }

    /**
     * Anti-flood guard: kill-switch + staleness TTL (see
     * DistributeFingerprintJob::distributionHalted for rationale).
     */
    private function distributionHalted(): bool
    {
        if (! config('fingerprintdevices.device_writes_enabled', true)) {
            Log::warning('FP_DIST_ADMS_KILLED_BY_SWITCH', [
                'user_id' => $this->userId,
            ]);

            return true;
        }

        $maxAge = max(1, (int) config('fingerprintdevices.distribution_max_age_hours', 72));
        // NOTE: `??` (not `=== null`) is REQUIRED here: rows serialized before
        // `dispatchedAt` existed unserialize with the typed property
        // UNINITIALIZED, and a direct read would throw. `??` safely yields null.
        $dispatchedAt = $this->dispatchedAt ?? null;
        $age = $dispatchedAt === null
            ? PHP_INT_MAX
            : max(0, time() - $dispatchedAt);

        if ($age > $maxAge * 3600) {
            Log::info('FP_DIST_ADMS_STALE_SKIPPED', [
                'user_id' => $this->userId,
                'age_seconds' => $age === PHP_INT_MAX ? -1 : $age,
            ]);

            return true;
        }

        return false;
    }
}
