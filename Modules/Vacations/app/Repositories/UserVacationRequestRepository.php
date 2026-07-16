<?php

namespace Modules\Vacations\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Vacations\Models\UserVacationRequest;

/**
 * Repository for `UserVacationRequest`.
 *
 * Read-mostly; the lifecycle writes (open / approve / reject / cancel)
 * are handled by `VacationRequestService` which uses this repository
 * for the actual `update` / `delete` calls.
 */
class UserVacationRequestRepository
{
    /**
     * Default eager-loaded relations to prevent N+1 when listing requests.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = [
        'user', 'manager', 'vacationType', 'balance',
    ];

    /**
     * Get a fresh query builder for the requests table.
     */
    public function query(): Builder
    {
        return UserVacationRequest::query();
    }

    /**
     * Get a paginated list of requests filtered by the supplied bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->query()->with($this->defaultWith), $filters)
            ->orderByDesc('requested_at')
            ->paginate($perPage);
    }

    /**
     * Find a request by its primary key.
     */
    public function findById(int $id): ?UserVacationRequest
    {
        return $this->query()
            ->with($this->defaultWith)
            ->find($id);
    }

    /**
     * Return every approved request that overlaps the supplied range for
     * a given user.
     *
     * @return Collection<int, UserVacationRequest>
     */
    public function approvedForUserInRange(int $userId, string $from, string $to): Collection
    {
        return $this->query()
            ->forUser($userId)
            ->approved()
            ->overlapping($from, $to)
            ->get();
    }

    /**
     * Persist a new request row.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): UserVacationRequest
    {
        return UserVacationRequest::create($data);
    }

    /**
     * Update an existing request row.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(UserVacationRequest $request, array $data): UserVacationRequest
    {
        $request->update($data);

        return $request->fresh($this->defaultWith);
    }

    /**
     * Soft delete the supplied request row.
     */
    public function delete(UserVacationRequest $request): bool
    {
        return $request->delete();
    }

    /**
     * Count requests matching the supplied filter bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters = []): int
    {
        return $this->applyFilters($this->query(), $filters)->count();
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

        $query->when($filters['manager_id'] ?? null, function (Builder $q, $managerId): void {
            $q->where('manager_id', (int) $managerId);
        });

        $query->when($filters['vacation_type_id'] ?? null, function (Builder $q, $typeId): void {
            $q->where('vacation_type_id', (int) $typeId);
        });

        $query->when($filters['status'] ?? null, function (Builder $q, $status): void {
            $q->where('status', $status);
        });

        $query->when($filters['start_date'] ?? null, function (Builder $q, $date): void {
            $q->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date);
        });

        $query->when($filters['from'] ?? null, function (Builder $q, $from): void {
            $q->where('end_date', '>=', $from);
        });

        $query->when($filters['to'] ?? null, function (Builder $q, $to): void {
            $q->where('start_date', '<=', $to);
        });

        $query->when($filters['year'] ?? null, function (Builder $q, $year): void {
            $q->where(function (Builder $sub) use ($year): void {
                $sub->whereYear('start_date', (int) $year)
                    ->orWhereYear('end_date', (int) $year);
            });
        });

        $query->when($filters['search'] ?? null, function (Builder $q, $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQ) use ($search): void {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%");
                    });
            });
        });

        return $query;
    }
}
