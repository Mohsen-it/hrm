<?php

namespace Modules\AttendanceIntegration\Models;

use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\FingerprintDevices\Models\FingerprintDevice;

class DeviceAdapter implements AttendanceDeviceInterface
{
    public function __construct(
        private FingerprintDevice $device,
    ) {}

    public function getId(): int
    {
        return (int) $this->device->id;
    }

    public function getName(): string
    {
        return (string) $this->device->name;
    }

    public function getSerialNumber(): ?string
    {
        return $this->device->serial_number;
    }

    public function getIpAddress(): string
    {
        return (string) $this->device->ip_address;
    }

    public function getPort(): int
    {
        return (int) ($this->device->port ?? 4370);
    }

    public function getCommKey(): string
    {
        return (string) ($this->device->comm_key ?? '0');
    }

    public function getTimeout(): int
    {
        return (int) ($this->device->timeout ?? 30);
    }

    public function getDriverName(): string
    {
        $typeName = strtolower($this->device->deviceType->manufacturer ?? '');

        return match (true) {
            str_contains($typeName, 'zkteco'), str_contains($typeName, 'zk') => 'zkteco',
            str_contains($typeName, 'suprema') => 'suprema',
            str_contains($typeName, 'hikvision'), str_contains($typeName, 'hik') => 'hikvision',
            default => config('attendanceintegration.default_driver', 'zkteco'),
        };
    }

    public function getStatus(): string
    {
        return (string) ($this->device->status ?? 'offline');
    }

    public function isPushEnabled(): bool
    {
        return (bool) ($this->device->is_push_enabled ?? false);
    }

    public function getLastSyncAt(): ?string
    {
        return $this->device->last_sync_at?->toDateTimeString();
    }

    public function getApiToken(): ?string
    {
        return $this->device->api_token;
    }

    public function getRawModel(): FingerprintDevice
    {
        return $this->device;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'serial_number' => $this->getSerialNumber(),
            'ip_address' => $this->getIpAddress(),
            'port' => $this->getPort(),
            'driver' => $this->getDriverName(),
            'status' => $this->getStatus(),
        ];
    }
}
