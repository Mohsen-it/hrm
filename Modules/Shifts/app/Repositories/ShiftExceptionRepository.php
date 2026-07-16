<?php

namespace Modules\Shifts\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shifts\Models\ShiftException;

/**
 * ShiftExceptionRepository — data access for the isolated interceptor table.
 */
class ShiftExceptionRepository
{
    protected array $defaultWith = ['employee', 'createdBy'];

    public function query(): Builder
    {
        return ShiftException::query();
    }

    /**
     * Find the FIRST active exception that intercepts the given employee on
     * the given date (strict fail-fast interceptor lookup).
     */
    public function findIntercepting(int $employeeId, string $date): ?ShiftException
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('employee_id', $employeeId)
            ->active()
            ->overlapping($date)
            ->whereIn('exception_type', ['leave', 'mission', 'swap', 'training'])
            ->orderBy('from_date')
            ->first();
    }

    /**
     * All active exceptions overlapping a date range for one or many employees.
     *
     * @param  array<int, int>|null  $employeeIds
     * @return Collection<int, ShiftException>
     */
    public function getActiveForRange(?array $employeeIds, string $from, string $to): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->when($employeeIds, fn (Builder $q) => $q->whereIn('employee_id', $employeeIds))
            ->active()
            ->where('from_date', '<=', $to)
            ->where('to_date', '>=', $from)
            ->orderBy('employee_id')
            ->orderBy('from_date')
            ->get();
    }

    public function create(array $data): ShiftException
    {
        return ShiftException::create($data);
    }

    public function cancel(ShiftException $exception): ShiftException
    {
        $exception->update(['status' => 'cancelled']);

        return $exception->fresh($this->defaultWith);
    }
}
