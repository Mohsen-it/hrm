<?php

namespace Modules\Companies\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Companies\Models\Company;

class CompanyRepository
{
    /**
     * Get a new query builder for the companies table.
     */
    public function query(): Builder
    {
        return Company::query();
    }

    /**
     * Get all companies with optional filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->query()->with('branches'), $filters)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get all active companies without pagination.
     *
     * @return Collection<int, Company>
     */
    public function getActive(): Collection
    {
        return $this->query()
            ->active()
            ->with('branches')
            ->get();
    }

    /**
     * Find a company by its primary key.
     */
    public function findById(int $id): ?Company
    {
        return $this->query()->with('branches')->find($id);
    }

    /**
     * Find a company by its unique code.
     */
    public function findByCode(string $code): ?Company
    {
        return $this->query()->where('company_code', $code)->first();
    }

    /**
     * Create a new company record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Company
    {
        return Company::create($data);
    }

    /**
     * Update the given company record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data): Company
    {
        $company->update($data);

        return $company->fresh();
    }

    /**
     * Soft delete the given company record.
     */
    public function delete(Company $company): bool
    {
        return $company->delete();
    }

    /**
     * Apply filters to the company query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('company_name', 'like', "%{$search}%")
                    ->orWhere('company_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        });

        $query->when(isset($filters['status']), function (Builder $q) use ($filters): void {
            $q->where('status', $filters['status']);
        });

        $query->when($filters['is_default'] ?? null, function (Builder $q): void {
            $q->default();
        });

        return $query;
    }
}
