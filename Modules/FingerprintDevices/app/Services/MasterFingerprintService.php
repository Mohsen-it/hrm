<?php

namespace Modules\FingerprintDevices\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Repositories\UserFingerprintRepository;

/**
 * MasterFingerprintService — manages the master fingerprint for each user.
 */
class MasterFingerprintService
{
    public function __construct(
        private UserFingerprintRepository $repository,
    ) {}

    public function getMasterFingerprint(int $userId): ?UserFingerprint
    {
        return $this->repository->getMasterForUser($userId);
    }

    public function getUserFingerprints(int $userId): Collection
    {
        return $this->repository->getForUser($userId);
    }

    public function storeFingerprint(array $data): UserFingerprint
    {
        $fingerprint = $this->repository->create($data);

        if ($data['is_master'] ?? false) {
            $this->repository->setMaster($data['user_id'], $fingerprint->id);
        }

        return $fingerprint->fresh();
    }

    public function setAsMaster(int $userId, int $fingerprintId): UserFingerprint
    {
        $this->repository->setMaster($userId, $fingerprintId);

        return $this->repository->findById($fingerprintId);
    }

    public function deleteFingerprint(UserFingerprint $fingerprint): bool
    {
        return $this->repository->delete($fingerprint);
    }

    public function deleteUserFingerprints(int $userId): int
    {
        return $this->repository->deleteForUser($userId);
    }

    public function getDeviceFingerprints(int $deviceId): Collection
    {
        return $this->repository->getForDevice($deviceId);
    }
}
