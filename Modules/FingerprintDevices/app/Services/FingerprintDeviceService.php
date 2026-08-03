<?php

namespace Modules\FingerprintDevices\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Services\RawAttendanceLogService;
use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Modules\AttendanceIntegration\Services\SchedulePunchClassifierService;
use Modules\FingerprintDevices\Http\Requests\StoreFingerprintDeviceRequest;
use Modules\FingerprintDevices\Http\Requests\UpdateFingerprintDeviceRequest;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Repositories\FingerprintDeviceRepository;
use Modules\Users\Models\User;

class FingerprintDeviceService
{
    public function __construct(
        private FingerprintDeviceRepository $repository,
        private DeviceAdapterResolver $adapterResolver,
        private RawAttendanceLogService $rawLogService,
        private SchedulePunchClassifierService $schedulePunchClassifier,
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
        $data = $request->validated();

        if (array_key_exists('comm_key', $data) && is_null($data['comm_key'])) {
            $data['comm_key'] = $device->comm_key ?? '0';
        }

        return $this->repository->update($device, $data);
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

        if (empty($records)) {
            return ['pulled' => 0, 'imported' => 0, 'sessions' => 0, 'records' => []];
        }

        $userMap = $this->buildUserMap($device, $adapter, $records);

        $rows = [];
        foreach ($records as $record) {
            $externalId = trim((string) ($record['user_id'] ?? ''));
            $uid = (int) ($record['uid'] ?? 0);
            $timestamp = $record['timestamp'] ?? null;
            if (($externalId === '' && $uid === 0) || ! $timestamp) {
                continue;
            }

            $userPk = $userMap['byUserId'][$externalId] ?? $userMap['byUid'][$uid] ?? null;

            $devicePunchType = $this->resolveDevicePunchType($record);
            $punchType = $this->schedulePunchClassifier->classify(
                $userPk,
                new \DateTimeImmutable($timestamp),
                $devicePunchType,
            );

            $rows[] = [
                'user_id' => $userPk,
                'device_id' => $device->id,
                'device_user_id' => $externalId ?: (string) $uid,
                'punch_time' => $timestamp,
                'punch_type' => $punchType->value,
                'verify_type' => $this->resolveVerifyType($record),
                'work_code' => (int) ($record['work_code'] ?? 0),
                'source' => 'device_pull',
                'ip_address' => $device->ip_address,
                'raw_data' => $record,
            ];
        }

        $imported = $this->rawLogService->bulkImport($rows);
        $processed = $this->rawLogService->processUnprocessedForDevice($device->id, $imported['inserted']);

        $this->repository->updateSyncTimestamp($device);

        return [
            'pulled' => count($records),
            'imported' => $imported['inserted'],
            'sessions' => $processed['sessions'],
            'records' => $records,
        ];
    }

    private function resolveDevicePunchType(array $record): PunchType
    {
        return match ((int) ($record['status'] ?? -1)) {
            0 => PunchType::CheckIn,
            1 => PunchType::CheckOut,
            2 => PunchType::BreakOut,
            3 => PunchType::BreakIn,
            default => PunchType::Unknown,
        };
    }

    private function resolveVerifyType(array $record): string
    {
        return match ((int) ($record['punch'] ?? 0)) {
            0, 1 => 'fingerprint',
            2, 3 => 'card',
            4 => 'password',
            default => 'fingerprint',
        };
    }

    private function buildUserMap(FingerprintDevice $device, DeviceAdapterInterface $adapter, array $records): array
    {
        $map = ['byUserId' => [], 'byUid' => []];

        $deviceUsers = $adapter->getUsers(
            $device->ip_address,
            $device->port,
            (string) $device->comm_key,
            $device->timeout
        );

        foreach ($deviceUsers as $du) {
            $uid = (int) ($du['uid'] ?? 0);
            $externalId = trim((string) ($du['user_id'] ?? ''));
            $name = (string) ($du['name'] ?? '');

            if ($externalId === '' && $uid === 0) {
                continue;
            }

            $user = null;
            if ($externalId !== '') {
                $user = User::query()
                    ->whereRaw('LOWER(employee_code) = LOWER(?)', [$externalId])
                    ->first();
            }

            if (! $user && $name !== '') {
                $user = User::query()
                    ->where('full_name_ar', $name)
                    ->orWhere('name', $name)
                    ->first();
            }

            if (! $user && $externalId !== '') {
                $user = User::query()
                    ->where('id', is_numeric($externalId) ? (int) $externalId : 0)
                    ->first();
            }

            if ($user) {
                if ($externalId !== '') {
                    $map['byUserId'][$externalId] = (int) $user->id;
                }
                if ($uid > 0) {
                    $map['byUid'][$uid] = (int) $user->id;
                }
            }
        }

        return $map;
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
