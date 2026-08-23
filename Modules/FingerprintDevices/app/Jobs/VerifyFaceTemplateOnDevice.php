<?php

namespace Modules\FingerprintDevices\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\DeviceCommand;
use Modules\FingerprintDevices\Models\FingerprintDevice;

/**
 * Verify that a face template was actually stored on the device after
 * the device ACKed the command.  Known iFace firmware quirk: the device
 * sometimes ACKs DATA UPDATE FACE with Return=0 but silently drops the
 * template.
 *
 * On verification failure the command is re-queued for retry.
 */
class VerifyFaceTemplateOnDevice implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $backoff = 5;

    public function __construct(
        public int $deviceId,
        public string $pin,
        public int $commandId,
    ) {}

    public function handle(): void
    {
        $device = FingerprintDevice::find($this->deviceId);
        if (! $device || ! $device->is_push_enabled) {
            return;
        }

        $command = DeviceCommand::find($this->commandId);
        if (! $command || $command->status !== DeviceCommand::STATUS_COMPLETED) {
            // Command was already re-queued or cancelled — nothing to verify.
            return;
        }

        $bridgeUrl = rtrim(config('attendanceintegration.drivers.zkteco.bridge_url', ''), '/');

        try {
            // Ask the device for the user's templates via the Python bridge.
            $resp = Http::timeout(30)->post("{$bridgeUrl}/device/get-templates", [
                'ip' => $device->ip_address,
                'port' => $device->port,
                'password' => (int) $device->comm_key,
                'uid' => $this->resolveDeviceUid($device, $bridgeUrl),
            ]);

            if (! $resp->successful()) {
                Log::warning('FACE_VERIFICATION_DEVICE_UNREACHABLE', [
                    'command_id' => $this->commandId,
                    'device_id' => $this->deviceId,
                    'pin' => $this->pin,
                ]);

                return; // Device unreachable — don't fail the command
            }

            $body = $resp->json() ?? [];
            $templates = $body['templates'] ?? [];

            // Check if any face template (fid >= 50) exists for this user
            $hasFace = collect($templates)->contains(fn (array $t) => ($t['fid'] ?? 0) >= 50);

            if ($hasFace) {
                Log::info('FACE_VERIFICATION_SUCCESS', [
                    'command_id' => $this->commandId,
                    'device_id' => $this->deviceId,
                    'pin' => $this->pin,
                    'template_count' => count($templates),
                ]);

                return;
            }

            // Template not found — device silently dropped it.
            // Re-queue the command for retry.
            Log::warning('FACE_VERIFICATION_FAILED_REQUEUING', [
                'command_id' => $this->commandId,
                'device_id' => $this->deviceId,
                'pin' => $this->pin,
                'templates_on_device' => count($templates),
            ]);

            $command->update([
                'status' => DeviceCommand::STATUS_PENDING,
                'retry_count' => 0,
                'max_retries' => 15,
                'sent_at' => null,
                'error_message' => 'Face template ACKed by device but verification found no face data — re-queued',
                'available_at' => now()->addSeconds(5),
            ]);
        } catch (\Throwable $e) {
            Log::warning('FACE_VERIFICATION_ERROR', [
                'command_id' => $this->commandId,
                'device_id' => $this->deviceId,
                'pin' => $this->pin,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve the device UID for the given PIN.
     */
    private function resolveDeviceUid(FingerprintDevice $device, string $bridgeUrl): int
    {
        $resp = Http::timeout(15)->post("{$bridgeUrl}/device/get-users", [
            'ip' => $device->ip_address,
            'port' => $device->port,
            'password' => (int) $device->comm_key,
        ]);

        if (! $resp->successful()) {
            return 0;
        }

        foreach (($resp->json()['users'] ?? []) as $u) {
            if (strval($u['user_id'] ?? '') === $this->pin) {
                return (int) ($u['uid'] ?? 0);
            }
        }

        return 0;
    }
}
