<?php

namespace Modules\AttendanceIntegration\Drivers\Hikvision;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\DTOs\DateRange;
use Modules\AttendanceIntegration\DTOs\DeviceInfo;
use Modules\AttendanceIntegration\DTOs\FingerprintTemplateData;
use Modules\AttendanceIntegration\DTOs\UserData;

class HikvisionAdapter implements DeviceAdapterInterface
{
    private string $bridgeUrl;

    private int $bridgeTimeout;

    public function __construct()
    {
        $this->bridgeUrl = rtrim((string) config('attendanceintegration.drivers.hikvision.api_url', 'http://127.0.0.1:5001'), '/');
        $this->bridgeTimeout = (int) config('attendanceintegration.drivers.hikvision.bridge_timeout', 30);
    }

    public function testConnection(string $ip, int $port, string $commKey = '', int $timeout = 30): bool
    {
        try {
            $credentials = $this->parseCredentials($commKey);
            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/test-connection", [
                    'ip' => $ip,
                    'port' => $port,
                    'username' => $credentials['username'],
                    'password' => $credentials['password'],
                    'timeout' => $timeout,
                ]);

            return $response->successful() && ($response->json('connected') === true);
        } catch (\Throwable $e) {
            Log::warning('HikvisionAdapter::testConnection failed', [
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
            Log::warning('HikvisionAdapter::getDeviceInfo failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function getUsers(string $ip, int $port, string $commKey = '', int $timeout = 30): array
    {
        try {
            $payload = $this->buildPayload($ip, $port, $commKey, $timeout);
            $response = Http::timeout(300)
                ->post("{$this->bridgeUrl}/device/get-users", $payload);

            return $response->successful() ? ($response->json('users') ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning('HikvisionAdapter::getUsers failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function addUser(string $ip, int $port, string $commKey, int $timeout, UserData $user): bool
    {
        try {
            $credentials = $this->parseCredentials($commKey);
            $payload = [
                'ip' => $ip,
                'port' => $port,
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'timeout' => $timeout,
                'employee_no' => $user->userId,
                'name' => $user->name,
                'password_value' => $user->password,
            ];

            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/add-user", $payload);

            return $response->successful() && ($response->json('success') === true);
        } catch (\Throwable $e) {
            Log::warning('HikvisionAdapter::addUser failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function deleteUser(string $ip, int $port, string $commKey, int $timeout, int $uid): bool
    {
        try {
            $credentials = $this->parseCredentials($commKey);
            $payload = [
                'ip' => $ip,
                'port' => $port,
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'timeout' => $timeout,
                'employee_no' => $uid,
            ];

            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/delete-user", $payload);

            return $response->successful() && ($response->json('success') === true);
        } catch (\Throwable $e) {
            Log::warning('HikvisionAdapter::deleteUser failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getAttendance(string $ip, int $port, string $commKey, int $timeout, ?DateRange $range = null): array
    {
        try {
            $credentials = $this->parseCredentials($commKey);
            $payload = [
                'ip' => $ip,
                'port' => $port,
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'timeout' => $timeout,
            ];

            if ($range) {
                $payload['start_time'] = $range->from;
                $payload['end_time'] = $range->to;
            }

            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/get-attendance", $payload);

            return $response->successful() ? ($response->json('attendance') ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning('HikvisionAdapter::getAttendance failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function getFingerprintTemplates(string $ip, int $port, string $commKey, int $timeout, int $uid): array
    {
        try {
            $credentials = $this->parseCredentials($commKey);
            $payload = [
                'ip' => $ip,
                'port' => $port,
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'timeout' => $timeout,
                'employee_no' => $uid,
            ];

            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/get-templates", $payload);

            return $response->successful() ? ($response->json('templates') ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning('HikvisionAdapter::getFingerprintTemplates failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function getAllFingerprintTemplates(string $ip, int $port, string $commKey = '', int $timeout = 30): array
    {
        try {
            $credentials = $this->parseCredentials($commKey);
            $payload = [
                'ip' => $ip,
                'port' => $port,
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'timeout' => $timeout,
            ];

            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/get-all-templates", $payload);

            return $response->successful() ? ($response->json('templates') ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning('HikvisionAdapter::getAllFingerprintTemplates failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function setFingerprintTemplate(string $ip, int $port, string $commKey, int $timeout, FingerprintTemplateData $template): bool
    {
        try {
            $credentials = $this->parseCredentials($commKey);
            $payload = [
                'ip' => $ip,
                'port' => $port,
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'timeout' => $timeout,
                'employee_no' => $template->uid,
                'finger_id' => $template->fingerId,
                'template_data' => $template->templateData,
            ];

            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/export-template", $payload);

            return $response->successful() && ($response->json('success') === true);
        } catch (\Throwable $e) {
            Log::warning('HikvisionAdapter::setFingerprintTemplate failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function clearAttendance(string $ip, int $port, string $commKey, int $timeout): bool
    {
        try {
            $credentials = $this->parseCredentials($commKey);
            $payload = [
                'ip' => $ip,
                'port' => $port,
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'timeout' => $timeout,
            ];

            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/clear-attendance", $payload);

            return $response->successful() && ($response->json('success') === true);
        } catch (\Throwable $e) {
            Log::warning('HikvisionAdapter::clearAttendance failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getDriverName(): string
    {
        return 'hikvision';
    }

    public function getUserDetail(string $ip, int $port, string $commKey, int $timeout, string $employeeNo): array
    {
        try {
            $credentials = $this->parseCredentials($commKey);
            $payload = [
                'ip' => $ip,
                'port' => $port,
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'timeout' => $timeout,
                'employee_no' => $employeeNo,
            ];

            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/get-user-detail", $payload);

            return $response->successful() ? ($response->json('detail') ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning('HikvisionAdapter::getUserDetail failed', ['employee_no' => $employeeNo, 'error' => $e->getMessage()]);

            return [];
        }
    }

    public function getUserDetailsBatch(string $ip, int $port, string $commKey, int $timeout, array $employeeNos): array
    {
        try {
            $credentials = $this->parseCredentials($commKey);
            $payload = [
                'ip' => $ip,
                'port' => $port,
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'timeout' => $timeout,
                'employee_nos' => $employeeNos,
            ];

            $response = Http::timeout(max($this->bridgeTimeout, count($employeeNos) * 5))
                ->post("{$this->bridgeUrl}/device/get-user-details-batch", $payload);

            return $response->successful() ? ($response->json('details') ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning('HikvisionAdapter::getUserDetailsBatch failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function getFacePhotos(string $ip, int $port, string $commKey, int $timeout): array
    {
        try {
            $credentials = $this->parseCredentials($commKey);
            $payload = [
                'ip' => $ip,
                'port' => $port,
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'timeout' => $timeout,
                'download' => true,
            ];

            $response = Http::timeout($this->bridgeTimeout)
                ->post("{$this->bridgeUrl}/device/get-face-photos", $payload);

            return $response->successful() ? ($response->json('photos') ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning('HikvisionAdapter::getFacePhotos failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Hikvision uses username:password as commKey (Digest Auth credentials).
     * Format: "username:password" or falls back to config env vars.
     */
    private function parseCredentials(string $commKey): array
    {
        if ($commKey && str_contains($commKey, ':')) {
            [$username, $password] = explode(':', $commKey, 2);

            return ['username' => $username, 'password' => $password];
        }

        return [
            'username' => config('attendanceintegration.drivers.hikvision.username', 'admin'),
            'password' => config('attendanceintegration.drivers.hikvision.password', ''),
        ];
    }

    private function buildPayload(string $ip, int $port, string $commKey, int $timeout): array
    {
        $credentials = $this->parseCredentials($commKey);

        return [
            'ip' => $ip,
            'port' => $port,
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'timeout' => $timeout,
        ];
    }
}
