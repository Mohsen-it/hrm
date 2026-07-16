<?php

namespace Modules\Shifts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shifts\Models\EmployeeShiftCategory;
use Modules\Shifts\Models\ShiftCategory;

class ShiftCategoryRepository
{
    /**
     * Default eager-loaded relations to prevent N+1.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = ['company', 'categoryTimeSchedule.timeSchedule'];

    /**
     * Get a new query builder for the shift categories table.
     */
    public function query(): Builder
    {
        return ShiftCategory::query();
    }

    /**
     * Get all shift categories with optional filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->query()
                ->with($this->defaultWith)
                ->withCount(['employees as active_employees_count' => function (Builder $q): void {
                    $q->whereNull('end_date');
                }]),
            $filters
        )
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get all shift categories belonging to a specific company.
     *
     * @return Collection<int, ShiftCategory>
     */
    public function getByCompany(int $companyId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Find a shift category by its primary key.
     */
    public function findById(int $id): ?ShiftCategory
    {
        return $this->query()
            ->with([...$this->defaultWith, 'employees'])
            ->find($id);
    }

    /**
     * Create a new shift category record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ShiftCategory
    {
        return ShiftCategory::create($data);
    }

    /**
     * Update the given shift category record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ShiftCategory $category, array $data): ShiftCategory
    {
        $category->update($data);

        return $category->fresh([...$this->defaultWith, 'employees']);
    }

    /**
     * Delete the given shift category record.
     */
    public function delete(ShiftCategory $category): bool
    {
        return $category->delete();
    }

    /**
     * Check whether any active employees are still assigned to this category.
     */
    public function hasActiveEmployees(int $categoryId): bool
    {
        return EmployeeShiftCategory::query()
            ->where('shift_category_id', $categoryId)
            ->whereNull('end_date')
            ->exists();
    }

    /**
     * Apply filters to the shift category query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('name', 'like', "%{$search}%");
            });
        });

        $query->when($filters['type'] ?? null, function (Builder $q, string $type): void {
            $q->where('type', $type);
        });

        $query->when($filters['company_id'] ?? null, function (Builder $q, int $companyId): void {
            $q->where('company_id', $companyId);
        });

        return $query;
    }
}
