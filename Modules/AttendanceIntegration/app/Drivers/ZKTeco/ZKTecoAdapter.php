<?php

namespace Modules\AttendanceIntegration\Drivers\ZKTeco;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\DTOs\DateRange;
use Modules\AttendanceIntegration\DTOs\DeviceInfo;
use Modules\AttendanceIntegration\DTOs\FingerprintTemplateData;
use Modules\AttendanceIntegration\DTOs\UserData;

class ZKTecoAdapter implements DeviceAdapterInterface
{
    private string $bridgeUrl;

    private int $bridgeTimeout;

    public function __construct()
    {
        $this->bridgeUrl = rtrim((string) config('attendanceintegration.drivers.zkteco.bridge_url', 'http://127.0.0.1:5000'), '/');
        $this->bridgeTimeout = (int) config('attendanceintegration.drivers.zkteco.bridge_timeout', 30);
    }

    public function testConnection(string $ip, int $port, string $commKey = '', int $timeout = 30): bool
    {
        try {
            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/test-connection", [
                    'ip' => $ip,
                    'port' => $port,
                    'password' => (int) $commKey,
                    'timeout' => $timeout,
                ]);

            return $response->successful() && ($response->json('connected') === true);
        } catch (\Throwable $e) {
            Log::warning('ZKTecoAdapter::testConnection failed', [
                'ip' => $ip,
                'port' => $port,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getDeviceInfo(string $ip, int $port, string $commKey = '', int $timeout = 30): ?DeviceInfo
    {
        try {
            $payload = $this->buildPayload($ip, $port, $commKey, $timeout);
            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/info", $payload);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->json() ?? [];
            $info = is_array($body['info'] ?? null) ? $body['info'] : $body;

            return DeviceInfo::fromArray($info);
        } catch (\Throwable $e) {
            Log::warning('ZKTecoAdapter::getDeviceInfo failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function getUsers(string $ip, int $port, string $commKey = '', int $timeout = 30): array
    {
        try {
            $payload = $this->buildPayload($ip, $port, $commKey, $timeout);
            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/get-users", $payload);

            return $response->successful() ? ($response->json('users') ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning('ZKTecoAdapter::getUsers failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function addUser(string $ip, int $port, string $commKey, int $timeout, UserData $user): bool
    {
        try {
            $payload = array_merge($this->buildPayload($ip, $port, $commKey, $timeout), [
                'uid' => $user->uid,
                'user_id' => $user->userId,
                'name' => $user->name,
                'password' => $user->password,
                'privilege' => $user->privilege,
            ]);

            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/add-user", $payload);

            return $response->successful() && ($response->json('success') === true);
        } catch (\Throwable $e) {
            Log::warning('ZKTecoAdapter::addUser failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function deleteUser(string $ip, int $port, string $commKey, int $timeout, int $uid): bool
    {
        try {
            $payload = array_merge($this->buildPayload($ip, $port, $commKey, $timeout), ['uid' => $uid]);
            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/delete-user", $payload);

            return $response->successful() && ($response->json('success') === true);
        } catch (\Throwable $e) {
            Log::warning('ZKTecoAdapter::deleteUser failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getAttendance(string $ip, int $port, string $commKey, int $timeout, ?DateRange $range = null): array
    {
        try {
            $payload = $this->buildPayload($ip, $port, $commKey, $timeout);
            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/get-attendance", $payload);

            return $response->successful() ? ($response->json('attendance') ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning('ZKTecoAdapter::getAttendance failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function getFingerprintTemplates(string $ip, int $port, string $commKey, int $timeout, int $uid): array
    {
        try {
            $payload = array_merge($this->buildPayload($ip, $port, $commKey, $timeout), ['uid' => $uid]);
            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/get-templates", $payload);

            return $response->successful() ? ($response->json('templates') ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning('ZKTecoAdapter::getFingerprintTemplates failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function setFingerprintTemplate(string $ip, int $port, string $commKey, int $timeout, FingerprintTemplateData $template): bool
    {
        try {
            $payload = array_merge($this->buildPayload($ip, $port, $commKey, $timeout), [
                'uid' => $template->uid,
                'finger_id' => $template->fingerId,
                'template_data' => $template->templateData,
            ]);

            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/export-template", $payload);

            return $response->successful() && ($response->json('success') === true);
        } catch (\Throwable $e) {
            Log::warning('ZKTecoAdapter::setFingerprintTemplate failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function clearAttendance(string $ip, int $port, string $commKey, int $timeout): bool
    {
        try {
            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/clear-attendance", $this->buildPayload($ip, $port, $commKey, $timeout));

            return $response->successful() && ($response->json('success') === true);
        } catch (\Throwable $e) {
            Log::warning('ZKTecoAdapter::clearAttendance failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getDriverName(): string
    {
        return 'zkteco';
    }

    public function getFacePhotos(string $ip, int $port, string $commKey, int $timeout): array
    {
        return [];
    }

    private function buildPayload(string $ip, int $port, string $commKey, int $timeout): array
    {
        return [
            'ip' => $ip,
            'port' => $port,
            'password' => (int) $commKey,
            'timeout' => $timeout,
        ];
    }
}
