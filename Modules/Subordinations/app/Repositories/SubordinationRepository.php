<?php

namespace Modules\Subordinations\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Subordinations\Models\Subordination;

class SubordinationRepository
{
    public function query(): Builder
    {
        return Subordination::query();
    }

    /**
     * Get a paginated list of subordinations filtered by the supplied bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->query(), $filters)
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): ?Subordination
    {
        return $this->query()->find($id);
    }

    public function findByCode(string $code): ?Subordination
    {
        return $this->query()->where('code', $code)->first();
    }

    /**
     * @return Collection<int, Subordination>
     */
    public function getActive(): Collection
    {
        return $this->query()
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Subordination
    {
        return Subordination::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Subordination $subordination, array $data): Subordination
    {
        $subordination->update($data);

        return $subordination->fresh();
    }

    public function delete(Subordination $subordination): bool
    {
        return $subordination->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('code', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%");
            });
        });

        $query->when(isset($filters['status']), function (Builder $q) use ($filters): void {
            $q->where('status', (int) $filters['status']);
        });

        return $query;
    }
}
