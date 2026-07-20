<?php

namespace Modules\FingerprintDevices\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Models\DeviceSyncLog;

/**
 * Repository for `DeviceSyncLog`.
 */
class DeviceSyncLogRepository
{
    public function query(): Builder
    {
        return DeviceSyncLog::query();
    }

    public function findById(int $id): ?DeviceSyncLog
    {
        return $this->query()->with(['device', 'user', 'pushResults'])->find($id);
    }

    public function create(array $data): DeviceSyncLog
    {
        return DeviceSyncLog::create($data);
    }

    public function update(DeviceSyncLog $log, array $data): DeviceSyncLog
    {
        $log->update($data);

        return $log->fresh();
    }

    public function getLastForDevice(int $deviceId): ?DeviceSyncLog
    {
        return $this->query()
            ->forDevice($deviceId)
            ->latest('started_at')
            ->first();
    }

    public function getRecentForDevice(int $deviceId, int $limit = 20): Collection
    {
        return $this->query()
            ->forDevice($deviceId)
            ->latest('started_at')
            ->limit($limit)
            ->get();
    }

    public function getFailed(int $limit = 50): Collection
    {
        return $this->query()
            ->failed()
            ->latest('started_at')
            ->limit($limit)
            ->get();
    }

    public function incrementSyncCount(int $deviceId): void
    {
        DB::table('fingerprint_devices')
            ->where('id', $deviceId)
            ->increment('sync_log_count');
    }

    public function delete(DeviceSyncLog $log): bool
    {
        return $log->delete();
    }
}
