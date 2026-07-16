<?php

namespace Modules\Shifts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shifts\Models\EmployeeShiftCategory;
use Modules\Shifts\Models\ShiftCategory;

class EmployeeShiftCategoryRepository
{
    /**
     * Default eager-loaded relations to prevent N+1.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = ['employee', 'shiftCategory'];

    /**
     * Get a new query builder for the employee shift categories table.
     */
    public function query(): Builder
    {
        return EmployeeShiftCategory::query();
    }

    /**
     * Get all employee shift category assignments with optional filters and pagination.
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
     * Find an employee shift category assignment by its primary key.
     */
    public function findById(int $id): ?EmployeeShiftCategory
    {
        return $this->query()
            ->with($this->defaultWith)
            ->find($id);
    }

    /**
     * Get the currently active assignment for the given employee.
     */
    public function getActiveAssignment(int $employeeId): ?EmployeeShiftCategory
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('employee_id', $employeeId)
            ->whereNull('end_date')
            ->first();
    }

    /**
     * Get all assignments that were active on the given date.
     *
     * @return Collection<int, EmployeeShiftCategory>
     */
    public function getAssignmentsForDate(string $date): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('start_date', '<=', $date)
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->get();
    }

    /**
     * Get the single assignment that scopes the employee on the given date.
     *
     * Used by the dynamic shift resolver for strict fail-fast scope resolution.
     */
    public function getAssignmentForDate(int $employeeId, string $date): ?EmployeeShiftCategory
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('employee_id', $employeeId)
            ->where('start_date', '<=', $date)
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * Create a new employee shift category assignment with a snapshot
     * of the category and its linked time schedule.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EmployeeShiftCategory
    {
        $category = ShiftCategory::with('categoryTimeSchedule.timeSchedule')
            ->find($data['shift_category_id']);

        $data['snapshot_data'] = json_encode([
            'category' => $category?->toArray(),
            'time_schedule' => $category?->categoryTimeSchedule?->timeSchedule?->toArray(),
        ]);

        return EmployeeShiftCategory::create($data);
    }

    /**
     * Close an active assignment by setting its end date.
     */
    public function closeAssignment(EmployeeShiftCategory $assignment, string $endDate): EmployeeShiftCategory
    {
        $assignment->end_date = $endDate;
        $assignment->save();

        return $assignment->fresh($this->defaultWith);
    }

    /**
     * Bulk assign a shift category to multiple employees.
     *
     * @param  array<int, int>  $employeeIds
     * @param  array<string, mixed>  $options
     * @return Collection<int, EmployeeShiftCategory>
     */
    public function bulkAssign(array $employeeIds, int $categoryId, string $startDate, array $options = []): Collection
    {
        $assignments = new Collection;

        foreach ($employeeIds as $employeeId) {
            $assignments->push($this->create(array_merge($options, [
                'employee_id' => $employeeId,
                'shift_category_id' => $categoryId,
                'start_date' => $startDate,
            ])));
        }

        return $assignments;
    }

    /**
     * Check whether the employee already has an assignment that overlaps
     * the given date range.
     */
    public function hasOverlappingAssignment(int $employeeId, string $startDate, ?string $endDate = null): bool
    {
        return $this->query()
            ->where('employee_id', $employeeId)
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $endDate ?? now()->toDateString())
            ->where(function (Builder $q) use ($startDate): void {
                $q->where('end_date', '>=', $startDate);
            })
            ->exists();
    }

    /**
     * Apply filters to the employee shift category query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->whereHas('employee', function (Builder $sub) use ($search): void {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        });

        $query->when($filters['employee_id'] ?? null, function (Builder $q, int $employeeId): void {
            $q->where('employee_id', $employeeId);
        });

        $query->when($filters['category_id'] ?? null, function (Builder $q, int $categoryId): void {
            $q->where('shift_category_id', $categoryId);
        });

        $query->when($filters['department_id'] ?? null, function (Builder $q, int $departmentId): void {
            $q->whereHas('employee', function (Builder $sub) use ($departmentId): void {
                $sub->where('department_id', $departmentId);
            });
        });

        $query->when($filters['status'] ?? null, function (Builder $q, string $status): void {
            match ($status) {
                'active' => $q->whereNull('end_date'),
                'closed' => $q->whereNotNull('end_date'),
                default => null,
            };
        });

        return $query;
    }
}
