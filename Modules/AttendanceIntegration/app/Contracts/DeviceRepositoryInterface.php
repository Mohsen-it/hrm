<?php

namespace Modules\AttendanceIntegration\Contracts;

interface DeviceRepositoryInterface
{
    public function findBySerial(string $serial): ?AttendanceDeviceInterface;

    public function findById(int $id): ?AttendanceDeviceInterface;

    public function markOnline(AttendanceDeviceInterface $device): void;

    public function markOffline(AttendanceDeviceInterface $device): void;

    public function updateSyncTimestamp(AttendanceDeviceInterface $device): void;

    public function getActive(): iterable;

    public function getOnline(): iterable;

    public function getOffline(): iterable;
}
