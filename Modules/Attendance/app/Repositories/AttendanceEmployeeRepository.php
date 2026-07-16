<?php

namespace Modules\Attendance\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Attendance\Models\AttendanceEmployee;

/**
 * Repository for AttendanceEmployee CRUD operations.
 */
class AttendanceEmployeeRepository
{
    protected array $defaultWith = ['employee', 'group'];

    public function query(): Builder
    {
        return AttendanceEmployee::query();
    }

    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->query()->with($this->defaultWith), $filters)
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): ?AttendanceEmployee
    {
        return $this->query()->with($this->defaultWith)->find($id);
    }

    public function findByEmployee(int $employeeId): ?AttendanceEmployee
    {
        return $this->query()->with($this->defaultWith)->where('emp_id', $employeeId)->first();
    }

    public function create(array $data): AttendanceEmployee
    {
        return AttendanceEmployee::create($data);
    }

    public function update(AttendanceEmployee $record, array $data): AttendanceEmployee
    {
        $record->update($data);

        return $record->fresh($this->defaultWith);
    }

    public function delete(AttendanceEmployee $record): bool
    {
        return $record->delete();
    }

    public function getByGroup(int $groupId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('group_id', $groupId)
            ->active()
            ->get();
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['group_id'] ?? null, fn (Builder $q, $val) => $q->where('group_id', (int) $val));
        $query->when($filters['emp_id'] ?? null, fn (Builder $q, $val) => $q->where('emp_id', (int) $val));
        $query->when($filters['status'] ?? null, fn (Builder $q, $val) => $q->where('status', $val));
        $query->when($filters['search'] ?? null, function (Builder $q, $search) {
            $q->whereHas('employee', fn (Builder $sub) => $sub->where('name', 'like', "%{$search}%"));
        });

        return $query;
    }
}
