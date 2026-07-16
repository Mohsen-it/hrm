<?php

namespace Modules\Departments\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Departments\Models\Department;

class DepartmentRepository
{
    /**
     * Default eager-loaded relations to prevent N+1.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = ['branch', 'company', 'manager', 'parent'];

    /**
     * Get a new query builder for the departments table.
     */
    public function query(): Builder
    {
        return Department::query();
    }

    /**
     * Get all departments with optional filters and pagination.
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
     * Find a department by its primary key with eager loaded relations.
     */
    public function findById(int $id): ?Department
    {
        return $this->query()
            ->with([...$this->defaultWith, 'children', 'users'])
            ->find($id);
    }

    /**
     * Get all departments belonging to a specific branch.
     *
     * @return Collection<int, Department>
     */
    public function getByBranch(int $branchId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('branch_id', $branchId)
            ->orderBy('department_name')
            ->get();
    }

    /**
     * Get all departments belonging to a specific company.
     *
     * @return Collection<int, Department>
     */
    public function getByCompany(int $companyId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('company_id', $companyId)
            ->orderBy('department_name')
            ->get();
    }

    /**
     * Get all root (top-level) departments (no parent).
     *
     * @return Collection<int, Department>
     */
    public function getRoots(): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->roots()
            ->orderBy('department_name')
            ->get();
    }

    /**
     * Create a new department record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Department
    {
        return Department::create($data);
    }

    /**
     * Update the given department record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department->fresh([...$this->defaultWith, 'children', 'users']);
    }

    /**
     * Soft delete the given department record.
     */
    public function delete(Department $department): bool
    {
        return $department->delete();
    }

    /**
     * Apply filters to the department query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('department_name', 'like', "%{$search}%")
                    ->orWhere('department_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        });

        $query->when($filters['company_id'] ?? null, function (Builder $q, int $companyId): void {
            $q->where('company_id', $companyId);
        });

        $query->when($filters['branch_id'] ?? null, function (Builder $q, int $branchId): void {
            $q->where('branch_id', $branchId);
        });

        $query->when($filters['parent_id'] ?? null, function (Builder $q, int $parentId): void {
            $q->where('parent_id', $parentId);
        });

        $query->when(isset($filters['status']), function (Builder $q) use ($filters): void {
            $q->where('status', $filters['status']);
        });

        $query->when($filters['roots_only'] ?? null, function (Builder $q): void {
            $q->roots();
        });

        return $query;
    }
}
