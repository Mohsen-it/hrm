<?php

namespace Modules\FingerprintDevices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\FingerprintDevices\Models\FingerprintDevice;

/**
 * BridgeBiometricSyncService — writes users and biometric templates to
 * ZKTeco terminals through the Python bridge (pyzk over TCP/4370).
 *
 * The ADMS/push channel is used for everything inbound (attendance,
 * BIODATA capture, heartbeats), but these hybrid terminals reject
 * biometric WRITE commands served over push (Return=-3/-30/-1xx on
 * every documented variant), while the pyzk write path is proven to
 * work across this fleet.
 */
class BridgeBiometricSyncService
{
    public function bridgeUrl(): string
    {
        return rtrim((string) config('attendanceintegration.drivers.zkteco.bridge_url'), '/');
    }

    /**
     * Create/update a user identity on the terminal (idempotent overwrite).
     */
    public function syncUser(FingerprintDevice $device, string $pin, string $name, int $privilege = 0): bool
    {
        try {
            $uid = $this->resolveUid($device, $pin);

            if ($uid === null) {
                $uid = $this->nextFreeUid($device);
            }

            $response = Http::timeout(15)->post($this->bridgeUrl().'/device/add-user', [
                'ip' => $device->ip_address,
                'port' => (int) $device->port,
                'password' => (int) $device->comm_key,
                'uid' => $uid,
                'user_id' => $pin,
                'name' => $name,
                'privilege' => $privilege,
            ]);

            $ok = (bool) ($response->json('success') ?? false);

            if (! $ok) {
                Log::warning('BRIDGE_USER_SYNC_FAILED', [
                    'device_id' => $device->id,
                    'pin' => $pin,
                    'response' => $response->json(),
                ]);
            }

            return $ok;
        } catch (\Throwable $e) {
            Log::error('BRIDGE_USER_SYNC_ERROR', [
                'device_id' => $device->id,
                'pin' => $pin,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Push the freshest captured face set of an employee to a terminal.
     *
     * @return array{pushed:int, failed:int}
     */
    public function syncFaceSet(FingerprintDevice $device, string $pin): array
    {
        $rows = DB::select(
            'SELECT uf.template_index, uf.template_data
             FROM user_fingerprints uf
             JOIN users u ON u.id = uf.user_id
             WHERE u.employee_code = ? AND uf.template_type = "face"
               AND uf.captured_at = (
                   SELECT MAX(c2.captured_at) FROM user_fingerprints c2
                   JOIN users u3 ON u3.id = c2.user_id
                   WHERE u3.employee_code = ? AND c2.template_index = uf.template_index
               )
             ORDER BY uf.template_index',
            [$pin, $pin],
        );

        return $this->pushTemplateRows($device, $pin, $rows);
    }

    /**
     * Push arbitrary template rows (index + base64 data) for a PIN.
     *
     * @param  iterable<object{template_index:int|string, template_data:string}>|array<int, array<string,mixed>>  $rows
     * @return array{pushed:int, failed:int}
     */
    public function pushTemplateRows(FingerprintDevice $device, string $pin, $rows): array
    {
        $uid = $this->resolveUid($device, $pin) ?? $this->nextFreeUid($device);

        $payloads = [];
        foreach ($rows as $row) {
            $index = (int) (is_array($row) ? $row['template_index'] : $row->template_index);
            $data = is_array($row) ? $row['template_data'] : $row->template_data;

            if (trim((string) $data) === '') {
                continue;
            }

            $payloads[] = [
                'uid' => $uid,
                'finger_id' => 50 + $index,
                'template_data' => (string) $data,
            ];
        }

        if (empty($payloads)) {
            return ['pushed' => 0, 'failed' => 0];
        }

        try {
            $response = Http::timeout(600)->retry(2, 2000)->post($this->bridgeUrl().'/device/export-templates-batch', [
                'ip' => $device->ip_address,
                'port' => (int) $device->port,
                'password' => (int) $device->comm_key,
                'templates' => $payloads,
            ]);

            $body = $response->json() ?? [];
            $pushed = (int) ($body['success_count'] ?? 0);
            $failed = (int) ($body['failed_count'] ?? count($payloads));

            if ($failed > 0) {
                Log::warning('BRIDGE_FACE_PUSH_PARTIAL', [
                    'device_id' => $device->id,
                    'pin' => $pin,
                    'pushed' => $pushed,
                    'failed' => $failed,
                    'errors' => array_slice(array_filter($body['results'] ?? [], fn ($r) => empty($r['success'])), 0, 5),
                ]);
            }

            return ['pushed' => $pushed, 'failed' => $failed];
        } catch (\Throwable $e) {
            Log::error('BRIDGE_FACE_PUSH_ERROR', [
                'device_id' => $device->id,
                'pin' => $pin,
                'error' => $e->getMessage(),
            ]);

            return ['pushed' => 0, 'failed' => count($payloads)];
        }
    }

    /** Map PIN → device UID (bridge returns the full user list). */
    public function resolveUid(FingerprintDevice $device, string $pin): ?int
    {
        try {
            $response = Http::timeout(10)->post($this->bridgeUrl().'/device/get-users', [
                'ip' => $device->ip_address,
                'port' => (int) $device->port,
                'password' => (int) $device->comm_key,
            ]);

            foreach (($response->json('users') ?? []) as $user) {
                if (strval($user['user_id'] ?? '') === $pin) {
                    return (int) ($user['uid'] ?? 0) ?: null;
                }
            }
        } catch (\Throwable $e) {
            Log::error('BRIDGE_UID_RESOLVE_ERROR', [
                'device_id' => $device->id,
                'pin' => $pin,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function nextFreeUid(FingerprintDevice $device): int
    {
        try {
            $response = Http::timeout(10)->post($this->bridgeUrl().'/device/get-users', [
                'ip' => $device->ip_address,
                'port' => (int) $device->port,
                'password' => (int) $device->comm_key,
            ]);

            $max = 0;
            foreach (($response->json('users') ?? []) as $user) {
                $max = max($max, (int) ($user['uid'] ?? 0));
            }

            return $max + 1;
        } catch (\Throwable) {
            return 1;
        }
    }
}
