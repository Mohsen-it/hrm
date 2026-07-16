<?php

namespace Modules\Positions\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Positions\Models\Position;

class PositionRepository
{
    /**
     * Default eager-loaded relations to prevent N+1.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = ['company', 'branch', 'department'];

    /**
     * Get a new query builder for the positions table.
     */
    public function query(): Builder
    {
        return Position::query();
    }

    /**
     * Get all positions with optional filters and pagination.
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
     * Get all active positions without pagination.
     *
     * @return Collection<int, Position>
     */
    public function getActive(): Collection
    {
        return $this->query()
            ->active()
            ->with($this->defaultWith)
            ->get();
    }

    /**
     * Get all positions belonging to a specific department.
     *
     * @return Collection<int, Position>
     */
    public function getByDepartment(int $departmentId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('department_id', $departmentId)
            ->orderBy('position_name')
            ->get();
    }

    /**
     * Get all positions belonging to a specific branch.
     *
     * @return Collection<int, Position>
     */
    public function getByBranch(int $branchId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('branch_id', $branchId)
            ->orderBy('position_name')
            ->get();
    }

    /**
     * Get all positions belonging to a specific company.
     *
     * @return Collection<int, Position>
     */
    public function getByCompany(int $companyId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('company_id', $companyId)
            ->orderBy('position_name')
            ->get();
    }

    /**
     * Find a position by its primary key.
     */
    public function findById(int $id): ?Position
    {
        return $this->query()
            ->with([...$this->defaultWith, 'users'])
            ->find($id);
    }

    /**
     * Create a new position record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Position
    {
        return Position::create($data);
    }

    /**
     * Update the given position record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Position $position, array $data): Position
    {
        $position->update($data);

        return $position->fresh([...$this->defaultWith, 'users']);
    }

    /**
     * Soft delete the given position record.
     */
    public function delete(Position $position): bool
    {
        return $position->delete();
    }

    /**
     * Apply filters to the position query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('position_name', 'like', "%{$search}%")
                    ->orWhere('position_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('requirements', 'like', "%{$search}%");
            });
        });

        $query->when($filters['company_id'] ?? null, function (Builder $q, int $companyId): void {
            $q->where('company_id', $companyId);
        });

        $query->when($filters['branch_id'] ?? null, function (Builder $q, int $branchId): void {
            $q->where('branch_id', $branchId);
        });

        $query->when($filters['department_id'] ?? null, function (Builder $q, int $departmentId): void {
            $q->where('department_id', $departmentId);
        });

        $query->when(isset($filters['status']), function (Builder $q) use ($filters): void {
            $q->where('status', $filters['status']);
        });

        return $query;
    }
}
