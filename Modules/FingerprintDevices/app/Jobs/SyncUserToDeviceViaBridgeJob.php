<?php

namespace Modules\FingerprintDevices\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\BridgeBiometricSyncService;

class SyncUserToDeviceViaBridgeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry across a ~100-minute window: terminals are routinely
     * unreachable for stretches (VPN/firewall), and user edits must
     * converge once the TCP path is back instead of dying on the
     * first attempt.
     */
    public int $tries = 6;

    /** @var array<int, int> Backoff seconds between attempts. */
    public array $backoff = [30, 120, 600, 1800, 3600];

    public int $timeout = 35;

    public function __construct(
        public int $deviceId,
        public string $pin,
        public string $name,
        public int $privilege = 0,
    ) {}

    public function handle(BridgeBiometricSyncService $bridgeSync): void
    {
        $device = FingerprintDevice::find($this->deviceId);
        if (! $device || ! $device->is_push_enabled) {
            return;
        }
        try {
            $ok = $bridgeSync->syncUser($device, $this->pin, $this->name, $this->privilege);
            Log::info('BRIDGE_USER_SYNC_JOB', [
                'device_id' => $this->deviceId,
                'pin' => $this->pin,
                'ok' => $ok,
            ]);
        } catch (\Throwable $e) {
            Log::error('BRIDGE_USER_SYNC_JOB_FAILED', [
                'device_id' => $this->deviceId,
                'pin' => $this->pin,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
