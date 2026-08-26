<?php

namespace Modules\FingerprintDevices\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Services\BridgeBiometricSyncService;
use Modules\Users\Models\User;

/**
 * DistributeFingerprintJob — propagates a freshly enrolled fingerprint to
 * every other terminal.
 *
 * This firmware does NOT accept fingerprint writes served over the push
 * channel (the terminal ACKs ``DATA UPDATE biodata Type=1`` with Return=0
 * but never stores the template).  The reliable channel is the pyzk
 * bridge: pull the template from the enrollment device over TCP, then
 * write it into each sibling device over TCP, verifying by read-back.
 */
class DistributeFingerprintJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $backoff = 15;

    public function __construct(
        public string $pin,
        public int $sourceDeviceId,
        public int $fingerId = 0,
        public string $templateData = '',
        public array $attributes = [],
    ) {}

    public function handle(BridgeBiometricSyncService $bridgeSync): void
    {
        $source = FingerprintDevice::find($this->sourceDeviceId);
        if (! $source) {
            Log::warning('FP_DIST_SOURCE_MISSING', ['pin' => $this->pin]);

            return;
        }

        $user = User::query()
            ->where('employee_code', $this->pin)
            ->first(['id', 'employee_code', 'full_name_ar', 'full_name_en', 'name']);

        $name = $user
            ? (string) ($user->full_name_ar ?: $user->full_name_en ?: $user->name ?: $this->pin)
            : $this->pin;

        // 1) Pull all fingerprint templates of this PIN from the source device.
        $srcUid = $this->uidOn($source, $this->pin);
        if ($srcUid === null) {
            Log::warning('FP_DIST_PIN_NOT_ON_SOURCE', [
                'pin' => $this->pin,
                'source_device_id' => $source->id,
            ]);

            return;
        }

        $templates = $this->pullTemplates($source, $srcUid);
        if (empty($templates)) {
            Log::info('FP_DIST_NOTHING_TO_PULL', ['pin' => $this->pin, 'source' => $source->serial_number]);

            return;
        }

        // 2) Push to every sibling terminal.
        $targets = FingerprintDevice::query()
            ->with('deviceType')
            ->where('is_push_enabled', true)
            ->where('id', '!=', $source->id)
            ->get()
            ->filter(fn (FingerprintDevice $d) => $d->getDriverName() === 'zkteco');

        foreach ($targets as $target) {
            try {
                // Identity first (clean Arabic name via bridge).
                $bridgeSync->syncUser($target, $this->pin, $name);

                $tgtUid = $this->uidOn($target, $this->pin);
                if ($tgtUid === null) {
                    continue;
                }

                $ok = 0;
                foreach ($templates as $t) {
                    $res = $this->exportTemplate($target, $tgtUid, (int) $t['fid'], (string) $t['template']);
                    if (! empty($res['success'])) {
                        $ok++;
                    } else {
                        Log::warning('FP_DIST_WRITE_FAILED', [
                            'pin' => $this->pin,
                            'target' => $target->serial_number,
                            'fid' => $t['fid'] ?? null,
                            'result' => $res,
                        ]);
                    }
                }

                Log::info('FINGERPRINT_BRIDGE_DISTRIBUTED', [
                    'pin' => $this->pin,
                    'target_device_id' => $target->id,
                    'templates_ok' => $ok,
                    'templates_total' => count($templates),
                ]);
            } catch (\Throwable $e) {
                Log::error('FINGERPRINT_DISTRIBUTION_ERROR', [
                    'pin' => $this->pin,
                    'target_device_id' => $target->id ?? null,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }

    /** @return array<int, array{fid:int, template:string}> */
    private function pullTemplates(FingerprintDevice $device, int $uid): array
    {
        $response = Http::timeout(300)->post($this->bridgeUrl().'/device/get-templates', [
            'ip' => $device->ip_address,
            'port' => (int) $device->port,
            'password' => (int) $device->comm_key,
            'uid' => $uid,
        ]);

        $all = $response->json('templates') ?? [];

        return array_values(array_filter(
            $all,
            fn (array $t) => (int) ($t['fid'] ?? 99) < 50 && (string) ($t['template'] ?? '') !== '',
        ));
    }

    private function exportTemplate(FingerprintDevice $device, int $uid, int $fid, string $template): array
    {
        $response = Http::timeout(120)->post($this->bridgeUrl().'/device/export-template', [
            'ip' => $device->ip_address,
            'port' => (int) $device->port,
            'password' => (int) $device->comm_key,
            'uid' => $uid,
            'finger_id' => $fid,
            'template_data' => $template,
        ]);

        return $response->json() ?? ['success' => false];
    }

    private function uidOn(FingerprintDevice $device, string $pin): ?int
    {
        try {
            $response = Http::timeout(120)->post($this->bridgeUrl().'/device/get-users', [
                'ip' => $device->ip_address,
                'port' => (int) $device->port,
                'password' => (int) $device->comm_key,
            ]);

            foreach (($response->json('users') ?? []) as $u) {
                if (strval($u['user_id'] ?? '') === $pin) {
                    return (int) ($u['uid'] ?? 0) ?: null;
                }
            }
        } catch (\Throwable $e) {
            Log::error('FP_DIST_UID_RESOLVE_ERROR', [
                'device_id' => $device->id,
                'pin' => $pin,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function bridgeUrl(): string
    {
        return rtrim((string) config('attendanceintegration.drivers.zkteco.bridge_url'), '/');
    }
}
