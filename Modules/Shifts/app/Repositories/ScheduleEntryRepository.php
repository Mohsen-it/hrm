<?php

namespace Modules\Shifts\Repositories;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shifts\Models\ScheduleEntry;

class ScheduleEntryRepository
{
    /**
     * Get all schedule entries with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ScheduleEntry::query()
            ->with(['employee', 'dutyCategory']);

        if (! empty($filters['schedule_period_id'])) {
            $query->where('schedule_period_id', $filters['schedule_period_id']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['duty_category_id'])) {
            $query->where('duty_category_id', $filters['duty_category_id']);
        }

        if (! empty($filters['day_status'])) {
            $query->where('day_status', $filters['day_status']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }

        return $query->orderBy('date')->paginate($perPage);
    }

    /**
     * Get entries for a specific employee in a date range.
     *
     * @return Collection<int, ScheduleEntry>
     */
    public function getForEmployeeInRange(int $employeeId, Carbon $from, Carbon $to): Collection
    {
        return ScheduleEntry::where('employee_id', $employeeId)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->orderBy('date')
            ->get();
    }

    /**
     * Get entries for a specific period.
     *
     * @return Collection<int, ScheduleEntry>
     */
    public function getForPeriod(int $periodId): Collection
    {
        return ScheduleEntry::where('schedule_period_id', $periodId)
            ->with(['employee', 'dutyCategory'])
            ->orderBy('employee_id')
            ->orderBy('date')
            ->get();
    }

    /**
     * Count work days for an employee in a period.
     */
    public function countWorkDays(int $employeeId, Carbon $from, Carbon $to): int
    {
        return ScheduleEntry::where('employee_id', $employeeId)
            ->where('date', '>=', $from)
            ->where('date', '<=', $to)
            ->where('day_status', 'WORK')
            ->count();
    }
}
