<?php

namespace Modules\Shifts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shifts\Models\RotationAssignment;

class RotationAssignmentRepository
{
    protected array $defaultWith = [
        'employee',
        'rotation',
        'rotationGroup.timeSchedule.categoryTimeSchedule',
        'rotationGroup.timeSchedule.category',
    ];

    public function query(): Builder
    {
        return RotationAssignment::query();
    }

    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->query()->with($this->defaultWith),
            $filters
        )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): ?RotationAssignment
    {
        return $this->query()
            ->with($this->defaultWith)
            ->find($id);
    }

    public function getActiveAssignment(int $employeeId): ?RotationAssignment
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('employee_id', $employeeId)
            ->whereNull('end_date')
            ->first();
    }

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

    public function getAssignmentForDate(int $employeeId, string $date): ?RotationAssignment
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

    public function create(array $data): RotationAssignment
    {
        return RotationAssignment::create($data);
    }

    public function closeAssignment(RotationAssignment $assignment, string $endDate): RotationAssignment
    {
        $assignment->end_date = $endDate;
        $assignment->save();

        return $assignment->fresh($this->defaultWith);
    }

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

        $query->when($filters['rotation_id'] ?? null, function (Builder $q, int $rotationId): void {
            $q->where('rotation_id', $rotationId);
        });

        $query->when($filters['rotation_group_id'] ?? null, function (Builder $q, int $groupId): void {
            $q->where('rotation_group_id', $groupId);
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
