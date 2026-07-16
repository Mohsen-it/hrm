<?php

namespace Modules\FingerprintDevices\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\FingerprintDevices\Models\UserFingerprint;

/**
 * Repository for `UserFingerprint`.
 */
class UserFingerprintRepository
{
    public function query(): Builder
    {
        return UserFingerprint::query();
    }

    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->query()->with(['user', 'device']),
            $filters
        )->latest()->paginate($perPage);
    }

    public function findById(int $id): ?UserFingerprint
    {
        return $this->query()->with(['user', 'device'])->find($id);
    }

    public function getForUser(int $userId): Collection
    {
        return $this->query()
            ->forUser($userId)
            ->with('device')
            ->orderBy('finger_id')
            ->get();
    }

    public function getMasterForUser(int $userId): ?UserFingerprint
    {
        return $this->query()
            ->forUser($userId)
            ->master()
            ->first();
    }

    public function getForDevice(int $deviceId): Collection
    {
        return $this->query()
            ->forDevice($deviceId)
            ->with('user')
            ->get();
    }

    public function create(array $data): UserFingerprint
    {
        return UserFingerprint::create($data);
    }

    public function update(UserFingerprint $fingerprint, array $data): UserFingerprint
    {
        $fingerprint->update($data);

        return $fingerprint->fresh();
    }

    public function delete(UserFingerprint $fingerprint): bool
    {
        return $fingerprint->delete();
    }

    public function deleteForUser(int $userId): int
    {
        return $this->query()->forUser($userId)->delete();
    }

    public function setMaster(int $userId, int $fingerprintId): bool
    {
        $this->query()->forUser($userId)->update(['is_master' => false]);

        return (bool) $this->query()->where('id', $fingerprintId)->update(['is_master' => true]);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['user_id'] ?? null, function (Builder $q, int $userId): void {
            $q->where('user_id', $userId);
        });

        $query->when($filters['device_id'] ?? null, function (Builder $q, int $deviceId): void {
            $q->where('device_id', $deviceId);
        });

        $query->when(isset($filters['is_master']), function (Builder $q) use ($filters): void {
            $q->where('is_master', (bool) $filters['is_master']);
        });

        $query->when($filters['finger_id'] ?? null, function (Builder $q, int $fingerId): void {
            $q->where('finger_id', $fingerId);
        });

        return $query;
    }
}
