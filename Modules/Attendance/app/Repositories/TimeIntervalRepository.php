<?php

namespace Modules\Attendance\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Attendance\Models\TimeInterval;

/**
 * Repository for TimeInterval CRUD operations.
 */
class TimeIntervalRepository
{
    protected array $defaultWith = ['company', 'breakTimes'];

    public function query(): Builder
    {
        return TimeInterval::query();
    }

    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->query()->with($this->defaultWith), $filters)
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): ?TimeInterval
    {
        return $this->query()->with([...$this->defaultWith, 'otPayCode'])->find($id);
    }

    public function create(array $data): TimeInterval
    {
        return TimeInterval::create($data);
    }

    public function update(TimeInterval $interval, array $data): TimeInterval
    {
        $interval->update($data);

        return $interval->fresh($this->defaultWith);
    }

    public function delete(TimeInterval $interval): bool
    {
        return $interval->delete();
    }

    public function getByCompany(int $companyId): Collection
    {
        return $this->query()
            ->with('breakTimes')
            ->where('company_id', $companyId)
            ->get();
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['company_id'] ?? null, fn (Builder $q, $val) => $q->where('company_id', (int) $val));
        $query->when($filters['search'] ?? null, function (Builder $q, $search) {
            $q->where('alias', 'like', "%{$search}%");
        });

        return $query;
    }
}
