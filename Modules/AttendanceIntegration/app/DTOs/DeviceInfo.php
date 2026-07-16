<?php

namespace Modules\AttendanceIntegration\DTOs;

final class DeviceInfo
{
    public function __construct(
        public readonly ?string $serialNumber = null,
        public readonly ?string $firmware = null,
        public readonly ?string $platform = null,
        public readonly ?string $deviceName = null,
        public readonly int $userCount = 0,
        public readonly int $fingerprintCount = 0,
        public readonly int $attendanceCount = 0,
        public readonly array $rawData = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            serialNumber: $data['serialnumber'] ?? $data['serial_number'] ?? null,
            firmware: $data['firmware'] ?? null,
            platform: $data['platform'] ?? null,
            deviceName: $data['device_name'] ?? null,
            userCount: (int) ($data['users_count'] ?? $data['user_count'] ?? 0),
            fingerprintCount: (int) ($data['templates_count'] ?? $data['fingerprint_count'] ?? 0),
            attendanceCount: (int) ($data['attendance_count'] ?? $data['attendance_log_count'] ?? 0),
            rawData: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'serial_number' => $this->serialNumber,
            'firmware' => $this->firmware,
            'platform' => $this->platform,
            'device_name' => $this->deviceName,
            'user_count' => $this->userCount,
            'fingerprint_count' => $this->fingerprintCount,
            'attendance_count' => $this->attendanceCount,
        ];
    }
}
