<?php

namespace Modules\FingerprintDevices\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\FingerprintDevices\Models\FingerprintDevice;

/**
 * Repository for `FingerprintDevice`.
 */
class FingerprintDeviceRepository
{
    public function query(): Builder
    {
        return FingerprintDevice::query();
    }

    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->query()->with(['deviceType', 'branch']),
            $filters
        )->latest()->paginate($perPage);
    }

    public function findById(int $id): ?FingerprintDevice
    {
        return $this->query()->with(['deviceType', 'branch'])->find($id);
    }

    public function findBySerial(string $serial): ?FingerprintDevice
    {
        return $this->query()->where('serial_number', $serial)->first();
    }

    public function getOnline(): Collection
    {
        return $this->query()->online()->with(['deviceType', 'branch'])->get();
    }

    public function getOffline(): Collection
    {
        return $this->query()->offline()->with(['deviceType', 'branch'])->get();
    }

    public function getActive(): Collection
    {
        return $this->query()->active()->with(['deviceType', 'branch'])->get();
    }

    public function create(array $data): FingerprintDevice
    {
        return FingerprintDevice::create($data);
    }

    public function update(FingerprintDevice $device, array $data): FingerprintDevice
    {
        $device->update($data);

        return $device->fresh();
    }

    public function delete(FingerprintDevice $device): bool
    {
        return $device->delete();
    }

    public function updateStatus(FingerprintDevice $device, string $status): FingerprintDevice
    {
        $data = ['status' => $status];
        if ($status === 'online') {
            $data['last_seen_at'] = now();
        }
        $device->update($data);

        return $device->fresh();
    }

    public function updateSyncTimestamp(FingerprintDevice $device): FingerprintDevice
    {
        $device->update(['last_synced_at' => now()]);

        return $device->fresh();
    }

    /**
     * Mark the device as online with a fresh `last_seen_at` timestamp.
     */
    public function markOnline(FingerprintDevice $device): FingerprintDevice
    {
        $device->update([
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        return $device->fresh();
    }

    /**
     * Mark the device as offline (used when a probe fails).
     */
    public function markOffline(FingerprintDevice $device): FingerprintDevice
    {
        $device->update(['status' => 'offline']);

        return $device->fresh();
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        });

        $query->when($filters['status'] ?? null, function (Builder $q, string $status): void {
            $q->where('status', $status);
        });

        $query->when($filters['device_type_id'] ?? null, function (Builder $q, int $typeId): void {
            $q->where('device_type_id', $typeId);
        });

        $query->when($filters['branch_id'] ?? null, function (Builder $q, int $branchId): void {
            $q->where('branch_id', $branchId);
        });

        $query->when($filters['connection_type'] ?? null, function (Builder $q, string $type): void {
            $q->where('connection_type', $type);
        });

        return $query;
    }
}
