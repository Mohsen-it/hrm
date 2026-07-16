<?php

namespace Modules\Branches\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Branches\Models\Branch;

class BranchRepository
{
    /**
     * Get a new query builder for the branches table.
     */
    public function query(): Builder
    {
        return Branch::query();
    }

    /**
     * Get all branches with optional filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->query()->with(['company', 'departments']), $filters)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get all active branches without pagination.
     *
     * @return Collection<int, Branch>
     */
    public function getActive(): Collection
    {
        return $this->query()
            ->active()
            ->with('company')
            ->get();
    }

    /**
     * Get all branches belonging to a specific company.
     *
     * @return Collection<int, Branch>
     */
    public function getByCompany(int $companyId): Collection
    {
        return $this->query()
            ->where('company_id', $companyId)
            ->with('departments')
            ->get();
    }

    /**
     * Find a branch by its primary key.
     */
    public function findById(int $id): ?Branch
    {
        return $this->query()->with(['company', 'departments', 'zones'])->find($id);
    }

    /**
     * Find a branch by its code within a company.
     */
    public function findByCode(string $code, int $companyId): ?Branch
    {
        return $this->query()
            ->where('branch_code', $code)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Create a new branch record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Branch
    {
        return Branch::create($data);
    }

    /**
     * Update the given branch record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Branch $branch, array $data): Branch
    {
        $branch->update($data);

        return $branch->fresh();
    }

    /**
     * Soft delete the given branch record.
     */
    public function delete(Branch $branch): bool
    {
        return $branch->delete();
    }

    /**
     * Apply filters to the branch query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('branch_name', 'like', "%{$search}%")
                    ->orWhere('branch_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        });

        $query->when($filters['company_id'] ?? null, function (Builder $q, int $companyId): void {
            $q->where('company_id', $companyId);
        });

        $query->when(isset($filters['status']), function (Builder $q) use ($filters): void {
            $q->where('status', $filters['status']);
        });

        $query->when($filters['is_main'] ?? null, function (Builder $q): void {
            $q->main();
        });

        return $query;
    }
}
