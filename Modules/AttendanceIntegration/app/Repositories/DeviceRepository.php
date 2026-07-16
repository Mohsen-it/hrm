<?php

namespace Modules\AttendanceIntegration\Repositories;

use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\Models\DeviceAdapter;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Repositories\FingerprintDeviceRepository;

class DeviceRepository implements DeviceRepositoryInterface
{
    public function __construct(
        private FingerprintDeviceRepository $repository,
    ) {}

    public function findBySerial(string $serial): ?AttendanceDeviceInterface
    {
        $device = $this->repository->findBySerial($serial);

        return $device ? new DeviceAdapter($device) : null;
    }

    public function findById(int $id): ?AttendanceDeviceInterface
    {
        $device = $this->repository->findById($id);

        return $device ? new DeviceAdapter($device) : null;
    }

    public function markOnline(AttendanceDeviceInterface $device): void
    {
        $model = $this->unwrap($device);
        if ($model) {
            $this->repository->markOnline($model);
        }
    }

    public function markOffline(AttendanceDeviceInterface $device): void
    {
        $model = $this->unwrap($device);
        if ($model) {
            $this->repository->markOffline($model);
        }
    }

    public function updateSyncTimestamp(AttendanceDeviceInterface $device): void
    {
        $model = $this->unwrap($device);
        if ($model) {
            $this->repository->updateSyncTimestamp($model);
        }
    }

    public function getActive(): iterable
    {
        return $this->repository->getActive()->map(fn (FingerprintDevice $d) => new DeviceAdapter($d));
    }

    public function getOnline(): iterable
    {
        return $this->repository->getOnline()->map(fn (FingerprintDevice $d) => new DeviceAdapter($d));
    }

    public function getOffline(): iterable
    {
        return $this->repository->getOffline()->map(fn (FingerprintDevice $d) => new DeviceAdapter($d));
    }

    private function unwrap(AttendanceDeviceInterface $device): ?FingerprintDevice
    {
        if ($device instanceof DeviceAdapter) {
            return $device->getRawModel();
        }

        return FingerprintDevice::find($device->getId());
    }
}
