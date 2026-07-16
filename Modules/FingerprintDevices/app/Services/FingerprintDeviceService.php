<?php

namespace Modules\FingerprintDevices\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Modules\FingerprintDevices\Http\Requests\StoreFingerprintDeviceRequest;
use Modules\FingerprintDevices\Http\Requests\UpdateFingerprintDeviceRequest;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Repositories\FingerprintDeviceRepository;

class FingerprintDeviceService
{
    public function __construct(
        private FingerprintDeviceRepository $repository,
        private DeviceAdapterResolver $adapterResolver,
    ) {}

    private function resolveAdapter(FingerprintDevice $device): DeviceAdapterInterface
    {
        $typeName = strtolower($device->deviceType->manufacturer ?? '');

        $driver = match (true) {
            str_contains($typeName, 'zkteco'), str_contains($typeName, 'zk') => 'zkteco',
            str_contains($typeName, 'suprema') => 'suprema',
            str_contains($typeName, 'hikvision'), str_contains($typeName, 'hik') => 'hikvision',
            default => config('attendanceintegration.default_driver', 'zkteco'),
        };

        return $this->adapterResolver->getAdapter($driver);
    }

    public function getAllDevices(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    public function getDeviceById(int $id): ?FingerprintDevice
    {
        return $this->repository->findById($id);
    }

    public function findBySerial(string $serial): ?FingerprintDevice
    {
        return $this->repository->findBySerial($serial);
    }

    public function markOnline(FingerprintDevice $device): FingerprintDevice
    {
        return $this->repository->markOnline($device);
    }

    public function markOffline(FingerprintDevice $device): FingerprintDevice
    {
        return $this->repository->markOffline($device);
    }

    public function getOnlineDevices(): Collection
    {
        return $this->repository->getOnline();
    }

    public function getOfflineDevices(): Collection
    {
        return $this->repository->getOffline();
    }

    public function createDevice(StoreFingerprintDeviceRequest $request): FingerprintDevice
    {
        return $this->repository->create($request->validatedPayload());
    }

    public function updateDevice(UpdateFingerprintDeviceRequest $request, FingerprintDevice $device): FingerprintDevice
    {
        return $this->repository->update($device, $request->validated());
    }

    public function deleteDevice(FingerprintDevice $device): bool
    {
        return $this->repository->delete($device);
    }

    public function testConnection(FingerprintDevice $device): bool
    {
        $adapter = $this->resolveAdapter($device);

        $connected = $adapter->testConnection(
            $device->ip_address,
            $device->port,
            (string) $device->comm_key,
            $device->timeout
        );

        $this->repository->updateStatus(
            $device,
            $connected ? 'online' : 'offline'
        );

        return $connected;
    }

    public function syncAttendance(FingerprintDevice $device): array
    {
        $adapter = $this->resolveAdapter($device);

        $records = $adapter->getAttendance(
            $device->ip_address,
            $device->port,
            (string) $device->comm_key,
            $device->timeout
        );

        if (! empty($records)) {
            $this->repository->updateSyncTimestamp($device);
        }

        return $records;
    }

    public function syncUsers(FingerprintDevice $device): array
    {
        $adapter = $this->resolveAdapter($device);

        return $adapter->getUsers(
            $device->ip_address,
            $device->port,
            (string) $device->comm_key,
            $device->timeout
        );
    }

    public function getDeviceStats(): array
    {
        $counts = $this->repository->query()
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $online = (int) ($counts['online'] ?? 0);
        $offline = (int) ($counts['offline'] ?? 0);
        $maintenance = (int) ($counts['maintenance'] ?? 0);
        $deactivated = (int) ($counts['deactivated'] ?? 0);
        $total = $online + $offline + $maintenance + $deactivated;

        return [
            'total' => $total,
            'online' => $online,
            'offline' => $offline,
            'maintenance' => $maintenance,
            'deactivated' => $deactivated,
        ];
    }
}
