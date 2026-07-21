<?php

namespace Modules\AttendanceIntegration\Contracts;

use Modules\AttendanceIntegration\DTOs\DateRange;
use Modules\AttendanceIntegration\DTOs\DeviceInfo;
use Modules\AttendanceIntegration\DTOs\FingerprintTemplateData;
use Modules\AttendanceIntegration\DTOs\UserData;

interface DeviceAdapterInterface
{
    public function testConnection(string $ip, int $port, string $commKey = '', int $timeout = 30): bool;

    public function getDeviceInfo(string $ip, int $port, string $commKey = '', int $timeout = 30): ?DeviceInfo;

    public function getUsers(string $ip, int $port, string $commKey = '', int $timeout = 30): array;

    public function addUser(string $ip, int $port, string $commKey, int $timeout, UserData $user): bool;

    public function deleteUser(string $ip, int $port, string $commKey, int $timeout, int $uid): bool;

    public function getAttendance(string $ip, int $port, string $commKey, int $timeout, ?DateRange $range = null): array;

    public function getFingerprintTemplates(string $ip, int $port, string $commKey, int $timeout, int $uid): array;

    /**
     * Fetch ALL fingerprint templates from the device in a single call.
     *
     * @return array<int, array{uid:int, fid:int, valid:int, template:string}>
     */
    public function getAllFingerprintTemplates(string $ip, int $port, string $commKey = '', int $timeout = 30): array;

    public function setFingerprintTemplate(string $ip, int $port, string $commKey, int $timeout, FingerprintTemplateData $template): bool;

    public function clearAttendance(string $ip, int $port, string $commKey, int $timeout): bool;

    public function getDriverName(): string;

    /**
     * Get face photos for all users from the device.
     * Returns array of ['employee_no' => string, 'face_url' => string, 'photo_base64' => string|null].
     * Default: empty array (not all devices support face photos).
     */
    public function getFacePhotos(string $ip, int $port, string $commKey, int $timeout): array;
}
