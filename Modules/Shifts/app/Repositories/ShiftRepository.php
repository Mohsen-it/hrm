<?php

namespace Modules\Shifts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shifts\Models\Shift;

class ShiftRepository
{
    /**
     * Default eager-loaded relations to prevent N+1.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = ['company', 'branch'];

    /**
     * Get a new query builder for the shifts table.
     */
    public function query(): Builder
    {
        return Shift::query();
    }

    /**
     * Get all shifts with optional filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->query()->with($this->defaultWith),
            $filters
        )
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get all active shifts without pagination.
     *
     * @return Collection<int, Shift>
     */
    public function getActive(): Collection
    {
        return $this->query()
            ->active()
            ->with($this->defaultWith)
            ->orderBy('shift_name')
            ->get();
    }

    /**
     * Get all shifts belonging to a specific company.
     *
     * @return Collection<int, Shift>
     */
    public function getByCompany(int $companyId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('company_id', $companyId)
            ->orderBy('shift_name')
            ->get();
    }

    /**
     * Get all shifts belonging to a specific branch.
     *
     * @return Collection<int, Shift>
     */
    public function getByBranch(int $branchId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('branch_id', $branchId)
            ->orderBy('shift_name')
            ->get();
    }

    /**
     * Find a shift by its primary key.
     */
    public function findById(int $id): ?Shift
    {
        return $this->query()
            ->with([...$this->defaultWith, 'users'])
            ->find($id);
    }

    /**
     * Create a new shift record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Shift
    {
        return Shift::create($data);
    }

    /**
     * Update the given shift record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Shift $shift, array $data): Shift
    {
        $shift->update($data);

        return $shift->fresh([...$this->defaultWith, 'users']);
    }

    /**
     * Soft delete the given shift record.
     */
    public function delete(Shift $shift): bool
    {
        return $shift->delete();
    }

    /**
     * Apply filters to the shift query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('shift_name', 'like', "%{$search}%")
                    ->orWhere('shift_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        });

        $query->when($filters['company_id'] ?? null, function (Builder $q, int $companyId): void {
            $q->where('company_id', $companyId);
        });

        $query->when($filters['branch_id'] ?? null, function (Builder $q, int $branchId): void {
            $q->where('branch_id', $branchId);
        });

        $query->when(isset($filters['status']), function (Builder $q) use ($filters): void {
            $q->where('status', $filters['status']);
        });

        return $query;
    }
}
