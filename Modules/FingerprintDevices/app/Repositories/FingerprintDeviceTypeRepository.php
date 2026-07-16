<?php

namespace Modules\FingerprintDevices\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\FingerprintDevices\Models\FingerprintDeviceType;

/**
 * Repository for `FingerprintDeviceType`.
 */
class FingerprintDeviceTypeRepository
{
    public function query(): Builder
    {
        return FingerprintDeviceType::query();
    }

    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->query()->withCount('devices'), $filters)
            ->latest()
            ->paginate($perPage);
    }

    public function getActive(): Collection
    {
        return $this->query()->active()->withCount('devices')->get();
    }

    public function findById(int $id): ?FingerprintDeviceType
    {
        return $this->query()->withCount('devices')->find($id);
    }

    public function create(array $data): FingerprintDeviceType
    {
        return FingerprintDeviceType::create($data);
    }

    public function update(FingerprintDeviceType $type, array $data): FingerprintDeviceType
    {
        $type->update($data);

        return $type->fresh();
    }

    public function delete(FingerprintDeviceType $type): bool
    {
        return $type->delete();
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('manufacturer', 'like', "%{$search}%")
                    ->orWhere('protocol', 'like', "%{$search}%");
            });
        });

        $query->when($filters['manufacturer'] ?? null, function (Builder $q, string $mfg): void {
            $q->where('manufacturer', $mfg);
        });

        $query->when(isset($filters['is_active']), function (Builder $q) use ($filters): void {
            $q->where('is_active', (bool) $filters['is_active']);
        });

        $query->when(isset($filters['supports_fingerprint']), function (Builder $q) use ($filters): void {
            $q->where('supports_fingerprint', (bool) $filters['supports_fingerprint']);
        });

        return $query;
    }
}
