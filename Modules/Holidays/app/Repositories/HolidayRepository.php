<?php

namespace Modules\Holidays\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Holidays\Models\Holiday;

/**
 * Repository for `Holiday`.
 *
 * Read-mostly; the controller's `store` / `update` / `destroy` are the
 * only writers. The integration service consults this repository when
 * patching attendance summaries.
 */
class HolidayRepository
{
    /**
     * Get a fresh query builder for the holidays table.
     */
    public function query(): Builder
    {
        return Holiday::query();
    }

    /**
     * Get a paginated list of holidays filtered by the supplied bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->query(), $filters)
            ->orderBy('date')
            ->orderBy('recurring_month')
            ->orderBy('recurring_day')
            ->paginate($perPage);
    }

    /**
     * Find a holiday by its primary key.
     */
    public function findById(int $id): ?Holiday
    {
        return $this->query()->find($id);
    }

    /**
     * Get every active holiday that has at least one occurrence in the
     * supplied range.
     *
     * @return Collection<int, Holiday>
     */
    public function getActiveInRange(string $from, string $to): Collection
    {
        return $this->query()
            ->active()
            ->inRange($from, $to)
            ->get();
    }

    /**
     * Persist a new holiday row.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Holiday
    {
        return Holiday::create($data);
    }

    /**
     * Update an existing holiday row.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Holiday $holiday, array $data): Holiday
    {
        $holiday->update($data);

        return $holiday->fresh();
    }

    /**
     * Soft delete the supplied holiday row.
     */
    public function delete(Holiday $holiday): bool
    {
        return $holiday->delete();
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
        $query->when($filters['search'] ?? null, function (Builder $q, $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        });

        $query->when($filters['category'] ?? null, function (Builder $q, $cat): void {
            $q->where('category', $cat);
        });

        $query->when(isset($filters['is_active']), function (Builder $q) use ($filters): void {
            $q->where('is_active', (bool) $filters['is_active']);
        });

        $query->when(isset($filters['is_recurring']), function (Builder $q) use ($filters): void {
            $q->where('is_recurring', (bool) $filters['is_recurring']);
        });

        $query->when($filters['date'] ?? null, function (Builder $q, $date): void {
            $q->where('date', $date);
        });

        $query->when($filters['from'] ?? null, function (Builder $q, $from): void {
            $q->where(function (Builder $sub) use ($from): void {
                $sub->where('date', '>=', $from)
                    ->orWhere('is_recurring', true);
            });
        });

        $query->when($filters['to'] ?? null, function (Builder $q, $to): void {
            $q->where(function (Builder $sub) use ($to): void {
                $sub->where('date', '<=', $to)
                    ->orWhere('is_recurring', true);
            });
        });

        $query->when($filters['year'] ?? null, function (Builder $q, $year): void {
            $q->where(function (Builder $sub) use ($year): void {
                $sub->whereYear('date', (int) $year)
                    ->orWhere('is_recurring', true);
            });
        });

        return $query;
    }
}
