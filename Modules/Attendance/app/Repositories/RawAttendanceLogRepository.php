<?php

namespace Modules\Attendance\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Attendance\Models\RawAttendanceLog;

/**
 * Repository for `RawAttendanceLog`.
 *
 * The RawAttendanceLog Repository is intentionally free of business logic: it
 * only builds query builders, applies filters, and persists rows. Business
 * rules (correlation into sessions, duplicate punch detection, etc.) live in
 * the Services layer.
 */
class RawAttendanceLogRepository
{
    /**
     * Default eager-loaded relations to prevent N+1 when listing logs.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = [
        'user',
        'device',
    ];

    /**
     * Get a fresh query builder for the raw attendance logs table.
     */
    public function query(): Builder
    {
        return RawAttendanceLog::query();
    }

    /**
     * Get a paginated list of raw logs filtered by the supplied filter bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->query()->with($this->defaultWith),
            $filters
        )
            ->latest('punch_time')
            ->paginate($perPage);
    }

    /**
     * Find a raw log by its primary key.
     */
    public function findById(int $id): ?RawAttendanceLog
    {
        return $this->query()
            ->with($this->defaultWith)
            ->find($id);
    }

    /**
     * Get all unprocessed raw logs, optionally restricted to a punch window.
     *
     * @return Collection<int, RawAttendanceLog>
     */
    public function getUnprocessed(?string $fromTime = null, ?string $toTime = null, int $limit = 0): Collection
    {
        $query = $this->query()
            ->unprocessed()
            ->when($fromTime, fn (Builder $q, $from) => $q->where('punch_time', '>=', $from))
            ->when($toTime, fn (Builder $q, $to) => $q->where('punch_time', '<=', $to))
            ->orderBy('punch_time');

        return $limit > 0 ? $query->limit($limit)->get() : $query->get();
    }

    /**
     * Get all raw logs for a specific user.
     *
     * @return Collection<int, RawAttendanceLog>
     */
    public function getByUser(int $userId, ?string $fromTime = null, ?string $toTime = null): Collection
    {
        return $this->query()
            ->forUser($userId)
            ->when($fromTime, fn (Builder $q, $from) => $q->where('punch_time', '>=', $from))
            ->when($toTime, fn (Builder $q, $to) => $q->where('punch_time', '<=', $to))
            ->orderBy('punch_time')
            ->get();
    }

    /**
     * Get all raw logs for a specific device.
     *
     * @return Collection<int, RawAttendanceLog>
     */
    public function getByDevice(int $deviceId, ?string $fromTime = null, ?string $toTime = null): Collection
    {
        return $this->query()
            ->forDevice($deviceId)
            ->when($fromTime, fn (Builder $q, $from) => $q->where('punch_time', '>=', $from))
            ->when($toTime, fn (Builder $q, $to) => $q->where('punch_time', '<=', $to))
            ->orderBy('punch_time')
            ->get();
    }

    /**
     * Count logs matching the filters (defaults to unprocessed).
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters = []): int
    {
        return $this->applyFilters($this->query(), $filters)->count();
    }

    /**
     * Persist a single raw log row.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RawAttendanceLog
    {
        return RawAttendanceLog::create($data);
    }

    /**
     * Persist many raw logs in a single bulk write.
     *
     * Each row must already be in the wire-format expected by the table; no
     * casting or timestamps are added by the repository (Eloquent's `insert`
     * bypasses them, so the caller should include `created_at` / `updated_at`
     * when applicable).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return int Number of inserted rows
     */
    public function bulkInsert(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        return RawAttendanceLog::insert($rows);
    }

    /**
     * Update the given raw log record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(RawAttendanceLog $log, array $data): RawAttendanceLog
    {
        $log->update($data);

        return $log->fresh();
    }

    /**
     * Mark the supplied raw log records as processed.
     *
     * @param  array<int, int>  $ids
     * @return int Number of affected rows
     */
    public function markProcessed(array $ids, ?\DateTimeInterface $at = null): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));

        if (empty($ids)) {
            return 0;
        }

        return RawAttendanceLog::whereIn('id', $ids)
            ->where('processed', false)
            ->update([
                'processed' => true,
                'processed_at' => $at ?? now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Soft delete the given raw log.
     */
    public function delete(RawAttendanceLog $log): bool
    {
        return $log->delete();
    }

    /**
     * Apply the supplied filter bag to the supplied query builder.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['user_id'] ?? null, function (Builder $q, $userId): void {
            $q->where('user_id', (int) $userId);
        });

        $query->when($filters['device_id'] ?? null, function (Builder $q, $deviceId): void {
            $q->where('device_id', (int) $deviceId);
        });

        $query->when($filters['device_user_id'] ?? null, function (Builder $q, $deviceUserId): void {
            $q->where('device_user_id', $deviceUserId);
        });

        $query->when($filters['punch_type'] ?? null, function (Builder $q, $punchType): void {
            $q->where('punch_type', $punchType);
        });

        $query->when($filters['verify_type'] ?? null, function (Builder $q, $verifyType): void {
            $q->where('verify_type', $verifyType);
        });

        $query->when($filters['source'] ?? null, function (Builder $q, $source): void {
            $q->where('source', $source);
        });

        $query->when(isset($filters['processed']), function (Builder $q) use ($filters): void {
            $q->where('processed', (bool) $filters['processed']);
        });

        $query->when($filters['from'] ?? null, function (Builder $q, $from): void {
            $q->where('punch_time', '>=', $from);
        });

        $query->when($filters['to'] ?? null, function (Builder $q, $to): void {
            $q->where('punch_time', '<=', $to);
        });

        $query->when($filters['search'] ?? null, function (Builder $q, $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('device_user_id', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        });

        return $query;
    }
}
