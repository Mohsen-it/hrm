<?php

namespace Modules\AttendanceIntegration\Drivers\Suprema;

use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\DTOs\DateRange;
use Modules\AttendanceIntegration\DTOs\DeviceInfo;
use Modules\AttendanceIntegration\DTOs\FingerprintTemplateData;
use Modules\AttendanceIntegration\DTOs\UserData;

class SupremaAdapter implements DeviceAdapterInterface
{
    public function testConnection(string $ip, int $port, string $commKey = '', int $timeout = 30): bool
    {
        throw new \RuntimeException('Suprema driver is not yet implemented.');
    }

    public function getDeviceInfo(string $ip, int $port, string $commKey = '', int $timeout = 30): ?DeviceInfo
    {
        throw new \RuntimeException('Suprema driver is not yet implemented.');
    }

    public function getUsers(string $ip, int $port, string $commKey = '', int $timeout = 30): array
    {
        throw new \RuntimeException('Suprema driver is not yet implemented.');
    }

    public function addUser(string $ip, int $port, string $commKey, int $timeout, UserData $user): bool
    {
        throw new \RuntimeException('Suprema driver is not yet implemented.');
    }

    public function deleteUser(string $ip, int $port, string $commKey, int $timeout, int $uid): bool
    {
        throw new \RuntimeException('Suprema driver is not yet implemented.');
    }

    public function getAttendance(string $ip, int $port, string $commKey, int $timeout, ?DateRange $range = null): array
    {
        throw new \RuntimeException('Suprema driver is not yet implemented.');
    }

    public function getFingerprintTemplates(string $ip, int $port, string $commKey, int $timeout, int $uid): array
    {
        throw new \RuntimeException('Suprema driver is not yet implemented.');
    }

    public function setFingerprintTemplate(string $ip, int $port, string $commKey, int $timeout, FingerprintTemplateData $template): bool
    {
        throw new \RuntimeException('Suprema driver is not yet implemented.');
    }

    public function clearAttendance(string $ip, int $port, string $commKey, int $timeout): bool
    {
        throw new \RuntimeException('Suprema driver is not yet implemented.');
    }

    public function getDriverName(): string
    {
        return 'suprema';
    }
}
