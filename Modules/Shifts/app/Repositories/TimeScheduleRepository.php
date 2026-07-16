<?php

namespace Modules\Shifts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shifts\Models\CategoryTimeSchedule;
use Modules\Shifts\Models\TimeSchedule;

class TimeScheduleRepository
{
    /**
     * Default eager-loaded relations to prevent N+1.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = ['company', 'breaks'];

    /**
     * Get a new query builder for the time schedules table.
     */
    public function query(): Builder
    {
        return TimeSchedule::query();
    }

    /**
     * Get all time schedules with optional filters and pagination.
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
     * Get all time schedules as a simple collection (no pagination).
     * Intended for dropdowns and selects.
     *
     * @return Collection<int, TimeSchedule>
     */
    public function getList(): Collection
    {
        return $this->query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Find a time schedule by its primary key.
     */
    public function findById(int $id): ?TimeSchedule
    {
        return $this->query()
            ->with($this->defaultWith)
            ->find($id);
    }

    /**
     * Create a new time schedule record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TimeSchedule
    {
        return TimeSchedule::create($data);
    }

    /**
     * Update the given time schedule record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(TimeSchedule $schedule, array $data): TimeSchedule
    {
        $schedule->update($data);

        return $schedule->fresh($this->defaultWith);
    }

    /**
     * Delete the given time schedule record.
     */
    public function delete(TimeSchedule $schedule): bool
    {
        return $schedule->delete();
    }

    /**
     * Check whether this time schedule is linked to any shift category.
     */
    public function isLinkedToCategory(int $scheduleId): bool
    {
        return CategoryTimeSchedule::query()
            ->where('time_schedule_id', $scheduleId)
            ->exists();
    }

    /**
     * Apply filters to the time schedule query.
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

        $query->when($filters['company_id'] ?? null, function (Builder $q, int $companyId): void {
            $q->where('company_id', $companyId);
        });

        return $query;
    }
}
