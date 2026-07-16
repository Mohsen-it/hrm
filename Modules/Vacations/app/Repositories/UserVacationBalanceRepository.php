<?php

namespace Modules\Vacations\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Vacations\Models\UserVacationBalance;

/**
 * Repository for `UserVacationBalance`.
 *
 * Read-mostly. The only writers are `VacationBalanceService::grant()`,
 * `VacationBalanceService::applyDelta()` and the year-end carry job.
 */
class UserVacationBalanceRepository
{
    /**
     * Get a fresh query builder for the balances table.
     */
    public function query(): Builder
    {
        return UserVacationBalance::query();
    }

    /**
     * Get a paginated list of balances filtered by the supplied bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->query()->with(['user', 'vacationType']), $filters)
            ->orderBy('year', 'desc')
            ->orderBy('user_id')
            ->paginate($perPage);
    }

    /**
     * Find a balance by its primary key.
     */
    public function findById(int $id): ?UserVacationBalance
    {
        return $this->query()
            ->with(['user', 'vacationType', 'transactions'])
            ->find($id);
    }

    /**
     * Find the (user, type, year) balance, or null when missing.
     */
    public function findForUserTypeYear(int $userId, int $typeId, int $year): ?UserVacationBalance
    {
        return $this->query()
            ->forUser($userId)
            ->forType($typeId)
            ->forYear($year)
            ->first();
    }

    /**
     * Return every balance the user holds across types and years.
     *
     * @return Collection<int, UserVacationBalance>
     */
    public function getForUser(int $userId): Collection
    {
        return $this->query()
            ->with('vacationType')
            ->forUser($userId)
            ->orderBy('year', 'desc')
            ->orderBy('vacation_type_id')
            ->get();
    }

    /**
     * Return every active balance for a year (used by year-end carry).
     *
     * @return Collection<int, UserVacationBalance>
     */
    public function getAllForYear(int $year): Collection
    {
        return $this->query()
            ->with('vacationType')
            ->forYear($year)
            ->get();
    }

    /**
     * Persist a new balance row.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): UserVacationBalance
    {
        return UserVacationBalance::create($data);
    }

    /**
     * Update an existing balance row.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(UserVacationBalance $balance, array $data): UserVacationBalance
    {
        $balance->update($data);

        return $balance->fresh(['user', 'vacationType']);
    }

    /**
     * Persist a balance row, creating it on first use.
     *
     * @param  array<string, mixed>  $data
     */
    public function upsert(array $keys, array $data): UserVacationBalance
    {
        $instance = $this->query()->where($keys)->first();
        if ($instance) {
            return $this->update($instance, $data);
        }

        return $this->create(array_merge($keys, $data));
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

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

        $query->when($filters['vacation_type_id'] ?? null, function (Builder $q, $typeId): void {
            $q->where('vacation_type_id', (int) $typeId);
        });

        $query->when($filters['year'] ?? null, function (Builder $q, $year): void {
            $q->where('year', (int) $year);
        });

        $query->when($filters['search'] ?? null, function (Builder $q, $search): void {
            $q->whereHas('user', function (Builder $sub) use ($search): void {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        });

        return $query;
    }
}
