<?php

namespace Modules\Shifts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shifts\Models\HoursTracking;

class HoursTrackingRepository
{
    /**
     * Default eager-loaded relations to prevent N+1.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = ['employee', 'shiftCategory'];

    /**
     * Get a new query builder for the hours tracking table.
     */
    public function query(): Builder
    {
        return HoursTracking::query();
    }

    /**
     * Get all hours tracking records with optional filters and pagination.
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
     * Find a hours tracking record by its primary key.
     */
    public function findById(int $id): ?HoursTracking
    {
        return $this->query()
            ->with($this->defaultWith)
            ->find($id);
    }

    /**
     * Upsert a tracking record using the unique composite key.
     *
     * @param  array<string, mixed>  $periodData
     */
    public function upsertTracking(int $employeeId, array $periodData): HoursTracking
    {
        return HoursTracking::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'period_start' => $periodData['period_start'],
                'period_end' => $periodData['period_end'],
                'period_type' => $periodData['period_type'],
            ],
            [
                'shift_category_id' => $periodData['shift_category_id'],
                'required_hours' => $periodData['required_hours'],
                'actual_hours' => $periodData['actual_hours'] ?? 0,
                'surplus_hours' => $periodData['surplus_hours'] ?? 0,
                'deficit_hours' => $periodData['deficit_hours'] ?? 0,
                'status' => $periodData['status'] ?? 'on_track',
            ]
        );
    }

    /**
     * Get the tracking record for a specific employee and period.
     */
    public function getTrackingForPeriod(int $employeeId, string $periodStart, string $periodEnd): ?HoursTracking
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('employee_id', $employeeId)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->first();
    }

    /**
     * Get all employees with a deficit in the given period range.
     *
     * @return Collection<int, HoursTracking>
     */
    public function getDeficitEmployees(string $periodStart, string $periodEnd): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->whereBetween('period_start', [$periodStart, $periodEnd])
            ->where('deficit_hours', '>', 0)
            ->get();
    }

    /**
     * Apply filters to the hours tracking query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['employee_id'] ?? null, function (Builder $q, int $employeeId): void {
            $q->where('employee_id', $employeeId);
        });

        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->whereHas('employee', function (Builder $sub) use ($search): void {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        });

        $query->when($filters['category_id'] ?? null, function (Builder $q, int $categoryId): void {
            $q->where('shift_category_id', $categoryId);
        });

        $query->when($filters['period_start'] ?? null, function (Builder $q, string $periodStart): void {
            $q->where('period_start', '>=', $periodStart);
        });

        $query->when($filters['period_end'] ?? null, function (Builder $q, string $periodEnd): void {
            $q->where('period_end', '<=', $periodEnd);
        });

        $query->when($filters['status'] ?? null, function (Builder $q, string $status): void {
            $q->where('status', $status);
        });

        return $query;
    }
}
