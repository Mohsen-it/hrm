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

    /** Get realtime ADMS face templates for the employee profile. */
    public function getFaceTemplatesForUser(int $userId): Collection
    {
        return $this->query()
            ->forUser($userId)
            ->where('template_type', 'face')
            ->with('device:id,name,serial_number')
            ->latest('updated_at')
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

    /** Find an ADMS template by its device, user and content hash. */
    public function findByTemplateHash(int $userId, string $deviceSerial, string $hash): ?UserFingerprint
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('device_serial', $deviceSerial)
            ->where('template_hash', $hash)
            ->first();
    }

    /**
     * Get saved ADMS face templates that may be distributed to another device.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, UserFingerprint>
     */
    public function getFaceTemplatesForDistribution(array $userIds, string $targetSerial): Collection
    {
        return $this->query()
            ->whereIn('user_id', $userIds)
            ->where('template_type', 'face')
            ->where('template_format', 'zkteco-face-push')
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->where(function (Builder $query) use ($targetSerial): void {
                $query->whereNull('device_serial')
                    ->orWhere('device_serial', '!=', $targetSerial);
            })
            ->with('user:id,employee_code,name,full_name_ar')
            ->orderBy('user_id')
            ->orderBy('finger_id')
            ->get();
    }

    /**
     * Get one complete source-device enrollment set for distribution.
     *
     * @return Collection<int, UserFingerprint>
     */
    public function getFaceTemplateSetForDistribution(
        int $userId,
        string $sourceSerial,
        string $setId,
    ): Collection {
        return $this->query()
            ->where('user_id', $userId)
            ->where('device_serial', $sourceSerial)
            ->where('face_template_set_id', $setId)
            ->where('template_type', 'face')
            ->where('template_format', 'zkteco-face-push')
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->with('user:id,employee_code,name,full_name_ar')
            ->orderBy('template_index')
            ->get();
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
