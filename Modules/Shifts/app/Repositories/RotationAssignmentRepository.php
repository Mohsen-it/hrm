<?php

namespace Modules\Shifts\Repositories;

use App\Traits\PaginatesResults;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shifts\Models\RotationAssignment;

class RotationAssignmentRepository
{
    use PaginatesResults;

    protected array $defaultWith = [
        'employee',
        'rotation.timeSchedule.categoryTimeSchedule',
        'rotation.timeSchedule.category',
        'rotationGroup',
    ];

    public function query(): Builder
    {
        return RotationAssignment::query();
    }

    public function getAll(array $filters = [], int|string $perPage = 20): LengthAwarePaginator
    {
        return $this->paginateOrAll(
            $this->applyFilters($this->query()->with($this->defaultWith), $filters)->latest(),
            $perPage
        );
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
            // Imports made before transfer validation can contain more than
            // one open row. The last transfer is the authoritative one.
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Get one current (open-ended) assignment per employee.
     *
     * Smart operational reports intentionally use this source rather than a
     * historical date window: the most recently saved transfer must replace
     * the old rotation/group immediately in their calculations and labels.
     *
     * @return Collection<int, RotationAssignment>
     */
    public function getLatestActiveAssignments(): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where(function (Builder $q): void {
                // Open assignments plus assignments whose end date is still in
                // the future. The rotation UI stores a far-future placeholder
                // end date (e.g. 2030-08-03) for "open-ended" assignments;
                // treating those as closed silently drops the employee from
                // every expected-attendance calculation.
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->unique('employee_id')
            ->values();
    }

    /**
     * Find an assignment for an employee whose inclusive date range conflicts
     * with the requested one.
     */
    public function findOverlappingAssignment(
        int $employeeId,
        string $startDate,
        ?string $endDate = null,
    ): ?RotationAssignment {
        return $this->findAllOverlappingAssignments($employeeId, $startDate, $endDate)
            ->first();
    }

    /**
     * Find every assignment for an employee whose inclusive date range
     * conflicts with the requested one.
     *
     * @return Collection<int, RotationAssignment>
     */
    public function findAllOverlappingAssignments(
        int $employeeId,
        string $startDate,
        ?string $endDate = null,
    ): Collection {
        $endDate ??= '9999-12-31';

        return $this->query()
            ->with($this->defaultWith)
            ->forEmployee($employeeId)
            ->where('start_date', '<=', $endDate)
            ->where(function (Builder $query) use ($startDate): void {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $startDate);
            })
            ->orderByDesc('start_date')
            ->get();
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
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Get every assignment whose date window overlaps the given inclusive range.
     *
     * Unlike getAssignmentsForDate (which only returns assignments active on a
     * single day), this captures assignments that start or end mid-range so
     * monthly schedule generation covers them correctly.
     */
    public function getAssignmentsOverlapping(string $from, string $to): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('start_date', '<=', $to)
            ->where(function (Builder $q) use ($from): void {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $from);
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
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
            ->orderByDesc('id')
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

    /**
     * Close every open-ended assignment for an employee.
     *
     * Imports and legacy data can leave more than one open row per employee;
     * closing all of them (instead of only the latest) guarantees a transfer
     * never leaves a stale, conflicting assignment behind.
     */
    public function closeAllActiveAssignments(int $employeeId, string $endDate): int
    {
        return $this->query()
            ->where('employee_id', $employeeId)
            ->whereNull('end_date')
            ->update(['end_date' => $endDate]);
    }

    /**
     * Delete all historical and active assignments belonging to a rotation.
     */
    public function deleteByRotation(int $rotationId): int
    {
        return $this->query()
            ->where('rotation_id', $rotationId)
            ->delete();
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
