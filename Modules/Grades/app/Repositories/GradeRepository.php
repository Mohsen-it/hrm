<?php

namespace Modules\Grades\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Grades\Models\Grade;

class GradeRepository
{
    /**
     * Default eager-loaded relations to prevent N+1.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = ['company'];

    /**
     * Get a new query builder for the grades table.
     */
    public function query(): Builder
    {
        return Grade::query();
    }

    /**
     * Get all grades with optional filters and pagination.
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
     * Get all active grades without pagination.
     *
     * @return Collection<int, Grade>
     */
    public function getActive(): Collection
    {
        return $this->query()
            ->active()
            ->with($this->defaultWith)
            ->orderBy('level')
            ->get();
    }

    /**
     * Get all grades belonging to a specific company.
     *
     * @return Collection<int, Grade>
     */
    public function getByCompany(int $companyId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('company_id', $companyId)
            ->orderBy('level')
            ->get();
    }

    /**
     * Find a grade by its primary key.
     */
    public function findById(int $id): ?Grade
    {
        return $this->query()
            ->with([...$this->defaultWith, 'users'])
            ->find($id);
    }

    /**
     * Create a new grade record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Grade
    {
        return Grade::create($data);
    }

    /**
     * Update the given grade record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Grade $grade, array $data): Grade
    {
        $grade->update($data);

        return $grade->fresh([...$this->defaultWith, 'users']);
    }

    /**
     * Soft delete the given grade record.
     */
    public function delete(Grade $grade): bool
    {
        return $grade->delete();
    }

    /**
     * Apply filters to the grade query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('grade_name', 'like', "%{$search}%")
                    ->orWhere('grade_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        });

        $query->when($filters['company_id'] ?? null, function (Builder $q, int $companyId): void {
            $q->where('company_id', $companyId);
        });

        $query->when($filters['level'] ?? null, function (Builder $q, int $level): void {
            $q->where('level', $level);
        });

        $query->when(isset($filters['status']), function (Builder $q) use ($filters): void {
            $q->where('status', $filters['status']);
        });

        return $query;
    }
}
