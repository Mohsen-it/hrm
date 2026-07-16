<?php

namespace Modules\Attendance\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Attendance\Models\GroupSchedule;

/**
 * Repository for GroupSchedule CRUD operations.
 */
class GroupScheduleRepository
{
    protected array $defaultWith = ['group', 'shift'];

    public function query(): Builder
    {
        return GroupSchedule::query();
    }

    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->query()->with($this->defaultWith), $filters)
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): ?GroupSchedule
    {
        return $this->query()->with([...$this->defaultWith, 'shift.details'])->find($id);
    }

    public function create(array $data): GroupSchedule
    {
        return GroupSchedule::create($data);
    }

    public function update(GroupSchedule $schedule, array $data): GroupSchedule
    {
        $schedule->update($data);

        return $schedule->fresh($this->defaultWith);
    }

    public function delete(GroupSchedule $schedule): bool
    {
        return $schedule->delete();
    }

    public function getActiveForGroup(int $groupId, string $date): ?GroupSchedule
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('group_id', $groupId)
            ->active()
            ->forDate($date)
            ->first();
    }

    public function getByGroup(int $groupId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('group_id', $groupId)
            ->get();
    }

    public function hasOverlap(int $groupId, string $startDate, string $endDate, ?int $excludeId = null): bool
    {
        $query = $this->query()
            ->where('group_id', $groupId)
            ->where(function (Builder $q) use ($startDate, $endDate) {
                $q->where(function (Builder $sub) use ($startDate, $endDate) {
                    $sub->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['group_id'] ?? null, fn (Builder $q, $val) => $q->where('group_id', (int) $val));
        $query->when($filters['shift_id'] ?? null, fn (Builder $q, $val) => $q->where('shift_id', (int) $val));
        $query->when($filters['date'] ?? null, fn (Builder $q, $val) => $q->forDate($val));

        return $query;
    }
}
