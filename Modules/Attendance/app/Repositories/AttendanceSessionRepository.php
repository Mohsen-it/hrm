<?php

namespace Modules\Attendance\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Attendance\Models\AttendanceSession;

/**
 * Repository for `AttendanceSession`.
 *
 * The repository is intentionally free of business logic: it only builds
 * query builders, applies filters, and persists rows. The actual calculation
 * of work / late / overtime minutes, the duplicate-punch detection, and the
 * shift resolution live in `AttendanceSessionService`.
 */
class AttendanceSessionRepository
{
    /**
     * Default eager-loaded relations to prevent N+1 when listing sessions.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = [
        'user',
        'shift',
    ];

    /**
     * Get a fresh query builder for the attendance sessions table.
     */
    public function query(): Builder
    {
        return AttendanceSession::query();
    }

    /**
     * Get a paginated list of sessions filtered by the supplied filter bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->query()->with($this->defaultWith),
            $filters
        )
            ->latest('attendance_date')
            ->latest('check_in_at')
            ->paginate($perPage);
    }

    /**
     * Find a session by its primary key.
     */
    public function findById(int $id): ?AttendanceSession
    {
        return $this->query()
            ->with([...$this->defaultWith, 'rawLog'])
            ->find($id);
    }

    /**
     * Fetch multiple sessions by their primary keys.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, AttendanceSession>
     */
    public function getByIds(array $ids): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));

        if (empty($ids)) {
            return new Collection;
        }

        return $this->query()
            ->with($this->defaultWith)
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * Get all sessions for a specific user.
     *
     * @return Collection<int, AttendanceSession>
     */
    public function getByUser(int $userId, ?string $from = null, ?string $to = null): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->forUser($userId)
            ->when($from, fn (Builder $q, $f) => $q->where('attendance_date', '>=', $f))
            ->when($to, fn (Builder $q, $t) => $q->where('attendance_date', '<=', $t))
            ->orderBy('attendance_date')
            ->orderBy('check_in_at')
            ->get();
    }

    /**
     * Count sessions matching the supplied filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters = []): int
    {
        return $this->applyFilters($this->query(), $filters)->count();
    }

    /**
     * Update the given session record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(AttendanceSession $session, array $data): AttendanceSession
    {
        $session->update($data);

        return $session->fresh($this->defaultWith);
    }

    /**
     * Soft delete the given session.
     */
    public function delete(AttendanceSession $session): bool
    {
        return $session->delete();
    }

    /**
     * Bulk update the `session_type` column for the supplied ids.
     *
     * @param  array<int, int>  $ids
     * @return int Number of affected rows
     */
    public function updateType(array $ids, string $type): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));

        if (empty($ids)) {
            return 0;
        }

        return $this->query()
            ->whereIn('id', $ids)
            ->update([
                'session_type' => $type,
                'updated_at' => now(),
            ]);
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

        $query->when($filters['shift_id'] ?? null, function (Builder $q, $shiftId): void {
            $q->where('shift_id', (int) $shiftId);
        });

        $query->when($filters['status'] ?? null, function (Builder $q, $status): void {
            $q->where('status', $status);
        });

        $query->when($filters['session_type'] ?? null, function (Builder $q, $type): void {
            $q->where('session_type', $type);
        });

        $query->when($filters['source'] ?? null, function (Builder $q, $source): void {
            $q->where('source', $source);
        });

        $query->when(isset($filters['open']), function (Builder $q) use ($filters): void {
            if ((bool) $filters['open']) {
                $q->whereNull('check_out_at');
            } else {
                $q->whereNotNull('check_out_at');
            }
        });

        $query->when($filters['date'] ?? null, function (Builder $q, $date): void {
            $q->where('attendance_date', $date);
        });

        $query->when($filters['from'] ?? null, function (Builder $q, $from): void {
            $q->where('attendance_date', '>=', $from);
        });

        $query->when($filters['to'] ?? null, function (Builder $q, $to): void {
            $q->where('attendance_date', '<=', $to);
        });

        $query->when($filters['search'] ?? null, function (Builder $q, $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('notes', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        });

        return $query;
    }
}
