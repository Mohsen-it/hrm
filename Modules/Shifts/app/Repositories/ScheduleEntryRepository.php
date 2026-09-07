<?php

namespace Modules\Shifts\Repositories;

use App\Traits\PaginatesResults;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shifts\Models\ScheduleEntry;

class ScheduleEntryRepository
{
    use PaginatesResults;

    /**
     * Get all schedule entries with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int|string $perPage = 20): LengthAwarePaginator
    {
        // Indexed lookup: (schedule_period_id, day_status) + (date, employee_id, day_status).
        // Conditional filters via when() (clean code, BR-10) — same output as before.
        $query = ScheduleEntry::query()
            ->with(['employee', 'dutyCategory'])
            ->when(! empty($filters['schedule_period_id']), fn ($q) => $q->where('schedule_period_id', $filters['schedule_period_id']))
            ->when(! empty($filters['employee_id']), fn ($q) => $q->where('employee_id', $filters['employee_id']))
            ->when(! empty($filters['duty_category_id']), fn ($q) => $q->where('duty_category_id', $filters['duty_category_id']))
            ->when(! empty($filters['day_status']), fn ($q) => $q->where('day_status', $filters['day_status']))
            ->when(
                ! empty($filters['date_from']) && ! empty($filters['date_to']),
                fn ($q) => $q->whereBetween('date', [$filters['date_from'], $filters['date_to']])
            )
            ->when(
                ! empty($filters['date_from']) && empty($filters['date_to']),
                fn ($q) => $q->where('date', '>=', $filters['date_from'])
            )
            ->when(
                empty($filters['date_from']) && ! empty($filters['date_to']),
                fn ($q) => $q->where('date', '<=', $filters['date_to'])
            );

        return $this->paginateOrAll($query->orderBy('date'), $perPage);
    }

    /**
     * Get entries for a specific employee in a date range.
     *
     * Indexed lookup: idx_schedule_entries_date_emp (date, employee_id, day_status).
     * Eager loads relations to prevent N+1 (output unchanged, relations only).
     *
     * @return Collection<int, ScheduleEntry>
     */
    public function getForEmployeeInRange(int $employeeId, Carbon $from, Carbon $to): Collection
    {
        return ScheduleEntry::where('employee_id', $employeeId)
            ->whereBetween('date', [$from, $to])
            ->with(['employee', 'dutyCategory'])
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
     *
     * Indexed lookup: idx_schedule_entries_date_emp (date, employee_id, day_status).
     * SQL-side COUNT (no PHP aggregation).
     */
    public function countWorkDays(int $employeeId, Carbon $from, Carbon $to): int
    {
        return ScheduleEntry::where('employee_id', $employeeId)
            ->whereBetween('date', [$from, $to])
            ->where('day_status', 'WORK')
            ->count();
    }
}
