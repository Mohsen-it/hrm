<?php

namespace Modules\Vacations\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Vacations\Models\VacationType;

/**
 * Repository for `VacationType`.
 *
 * Catalog table — read-mostly. Writes are CRUD flows handled by the
 * VacationTypeService.
 */
class VacationTypeRepository
{
    /**
     * Get a fresh query builder for the vacation types table.
     */
    public function query(): Builder
    {
        return VacationType::query();
    }

    /**
     * Get a paginated list of vacation types filtered by the supplied bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->query(), $filters)
            ->ordered()
            ->paginate($perPage);
    }

    /**
     * Find a vacation type by its primary key.
     */
    public function findById(int $id): ?VacationType
    {
        return $this->query()->find($id);
    }

    /**
     * Find a vacation type by its machine code (annual, sick, ...).
     */
    public function findByCode(string $code): ?VacationType
    {
        return $this->query()->where('code', $code)->first();
    }

    /**
     * Return all active vacation types ordered for select boxes.
     *
     * @return Collection<int, VacationType>
     */
    public function listActive(): Collection
    {
        return $this->query()
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Persist a new vacation type.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): VacationType
    {
        return VacationType::create($data);
    }

    /**
     * Update an existing vacation type.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(VacationType $type, array $data): VacationType
    {
        $type->update($data);

        return $type->fresh();
    }

    /**
     * Soft delete the supplied vacation type.
     */
    public function delete(VacationType $type): bool
    {
        return $type->delete();
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

        $query->when(isset($filters['is_active']), function (Builder $q) use ($filters): void {
            $q->where('is_active', (bool) $filters['is_active']);
        });

        $query->when(isset($filters['is_paid']), function (Builder $q) use ($filters): void {
            $q->where('is_paid', (bool) $filters['is_paid']);
        });

        $query->when(isset($filters['requires_approval']), function (Builder $q) use ($filters): void {
            $q->where('requires_approval', (bool) $filters['requires_approval']);
        });

        return $query;
    }
}
