<?php

namespace Modules\FingerprintDevices\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Modules\FingerprintDevices\Models\DeviceSyncLog;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\DevicePushService;

/**
 * PushUsersToDeviceJob — pushes a list of users to a single fingerprint device.
 *
 * Dispatched by {@see DeviceFullSyncController::push()} when the user count
 * exceeds 200 to avoid blocking the browser. Progress is recorded against
 * the supplied `sync_log_id` so the operator can poll its status.
 */
class PushUsersToDeviceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 1800;

    public function __construct(
        public int $deviceId,
        public array $userIds,
        public ?int $syncLogId = null,
        public int $chunkSize = 50,
    ) {}

    public function handle(DevicePushService $service): void
    {
        if (empty($this->userIds)) {
            return;
        }

        Log::info('PushUsersToDeviceJob: starting', [
            'device_id' => $this->deviceId,
            'user_count' => count($this->userIds),
            'sync_log_id' => $this->syncLogId,
        ]);

        // Process in chunks of 50 to keep memory bounded and request timeouts small.
        foreach (array_chunk($this->userIds, $this->chunkSize) as $chunk) {
            $device = FingerprintDevice::find($this->deviceId);
            if (! $device) {
                return;
            }

            $adapter = $this->resolveAdapter($device);

            $service->pushUsers(
                $device,
                $adapter,
                $chunk,
                DeviceSyncLog::find($this->syncLogId) ?? $this->ensureSyncLog($device),
            );
        }

        Log::info('PushUsersToDeviceJob: complete', [
            'device_id' => $this->deviceId,
            'sync_log_id' => $this->syncLogId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PushUsersToDeviceJob failed permanently', [
            'device_id' => $this->deviceId,
            'user_count' => count($this->userIds),
            'error' => $exception->getMessage(),
        ]);

        if ($this->syncLogId) {
            DeviceSyncLog::where('id', $this->syncLogId)
                ->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'errors' => array_merge(
                        (array) DeviceSyncLog::find($this->syncLogId)?->errors,
                        [$exception->getMessage()],
                    ),
                ]);
        }
    }

    public function middleware(): array
    {
        return [new RateLimited('device-push')];
    }

    private function ensureSyncLog(FingerprintDevice $device): DeviceSyncLog
    {
        if ($this->syncLogId) {
            $existing = DeviceSyncLog::find($this->syncLogId);
            if ($existing) {
                return $existing;
            }
        }

        return DeviceSyncLog::create([
            'device_id' => $device->id,
            'direction' => 'push',
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    private function resolveAdapter(FingerprintDevice $device): DeviceAdapterInterface
    {
        $typeName = strtolower($device->deviceType->manufacturer ?? '');

        $driver = match (true) {
            str_contains($typeName, 'zkteco'), str_contains($typeName, 'zk') => 'zkteco',
            str_contains($typeName, 'suprema') => 'suprema',
            str_contains($typeName, 'hikvision'), str_contains($typeName, 'hik') => 'hikvision',
            default => config('attendanceintegration.default_driver', 'zkteco'),
        };

        return app(DeviceAdapterResolver::class)->getAdapter($driver);
    }
}
