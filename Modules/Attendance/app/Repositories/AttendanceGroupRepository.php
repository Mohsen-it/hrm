<?php

namespace Modules\Attendance\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Attendance\Models\AttendanceGroup;

/**
 * Repository for AttendanceGroup CRUD operations.
 */
class AttendanceGroupRepository
{
    protected array $defaultWith = ['company', 'employees'];

    public function query(): Builder
    {
        return AttendanceGroup::query();
    }

    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->query()->with($this->defaultWith), $filters)
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): ?AttendanceGroup
    {
        return $this->query()->with([...$this->defaultWith, 'policy'])->find($id);
    }

    public function create(array $data): AttendanceGroup
    {
        return AttendanceGroup::create($data);
    }

    public function update(AttendanceGroup $group, array $data): AttendanceGroup
    {
        $group->update($data);

        return $group->fresh($this->defaultWith);
    }

    public function delete(AttendanceGroup $group): bool
    {
        return $group->delete();
    }

    public function getByCompany(int $companyId): Collection
    {
        return $this->query()
            ->with('employees')
            ->where('company_id', $companyId)
            ->active()
            ->get();
    }

    public function countEmployeesInGroup(int $groupId): int
    {
        return AttendanceGroup::find($groupId)?->employees()->active()->count() ?? 0;
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['company_id'] ?? null, fn (Builder $q, $val) => $q->where('company_id', (int) $val));
        $query->when($filters['status'] ?? null, fn (Builder $q, $val) => $q->where('status', $val));
        $query->when($filters['search'] ?? null, function (Builder $q, $search) {
            $q->where(function (Builder $sub) use ($search) {
                $sub->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        });

        return $query;
    }
}
