<?php

namespace Modules\FingerprintDevices\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\FingerprintDevices\Models\DevicePushResult;

/**
 * Repository for `DevicePushResult`.
 */
class DevicePushResultRepository
{
    public function query(): Builder
    {
        return DevicePushResult::query();
    }

    public function findById(int $id): ?DevicePushResult
    {
        return $this->query()->find($id);
    }

    public function create(array $data): DevicePushResult
    {
        return DevicePushResult::create($data);
    }

    /**
     * Bulk-insert many result rows in a single SQL statement.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function createMany(array $rows): bool
    {
        if (empty($rows)) {
            return false;
        }

        $now = now();
        $rows = array_map(function (array $row) use ($now): array {
            $row['created_at'] = $row['created_at'] ?? $now;
            $row['updated_at'] = $row['updated_at'] ?? $now;

            return $row;
        }, $rows);

        return DB::table('device_push_results')->insert($rows);
    }

    public function getFailedForLog(int $syncLogId): Collection
    {
        return $this->query()
            ->where('sync_log_id', $syncLogId)
            ->failed()
            ->get();
    }

    public function getFailedForDevice(int $deviceId, int $limit = 100): Collection
    {
        return $this->query()
            ->forDevice($deviceId)
            ->failed()
            ->latest('attempted_at')
            ->limit($limit)
            ->get();
    }

    public function incrementRetry(int $id): void
    {
        DB::table('device_push_results')
            ->where('id', $id)
            ->increment('retry_count');
    }

    /**
     * Idempotency check: did the same (device, user, type) succeed recently?
     */
    public function hasRecentSuccess(
        int $deviceId,
        int $userId,
        string $recordType,
        int $minutesWindow = 60,
    ): bool {
        return $this->query()
            ->forDevice($deviceId)
            ->ofType($recordType)
            ->successful()
            ->where('target_user_id', $userId)
            ->where('attempted_at', '>=', now()->subMinutes($minutesWindow))
            ->exists();
    }

    public function getStatsForLog(int $syncLogId): array
    {
        return DB::table('device_push_results')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->where('sync_log_id', $syncLogId)
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();
    }
}
