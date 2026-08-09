<?php

namespace Modules\Attendance\Repositories;

use App\Traits\PaginatesResults;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Attendance\Models\DailyAttendanceSummary;

/**
 * Repository for `DailyAttendanceSummary`.
 *
 * Read-mostly; the only writes performed here are status / notes patches
 * coming from the daily-summaries controller. Heavy recalculation writes
 * live in `DailyAttendanceSummaryService`.
 */
class DailyAttendanceSummaryRepository
{
    use PaginatesResults;

    /**
     * Default eager-loaded relations to prevent N+1 when listing summaries.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = [
        'user.department',
        'shift',
    ];

    /**
     * Get a fresh query builder for the daily summaries table.
     */
    public function query(): Builder
    {
        return DailyAttendanceSummary::query();
    }

    /**
     * Get a paginated list of summaries filtered by the supplied filter bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int|string $perPage = 20): LengthAwarePaginator
    {
        return $this->paginateOrAll(
            $this->applyFilters($this->query()->with($this->defaultWith), $filters)
                ->orderBy('summary_date', 'desc')
                ->orderBy('user_id'),
            $perPage
        );
    }

    /**
     * Compute status breakdown + timing totals for the supplied filter bag.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getStats(array $filters = []): array
    {
        $rows = $this->applyFilters($this->query(), $filters)
            ->selectRaw('status, COUNT(*) as count, SUM(total_work_minutes) as work_minutes, SUM(total_overtime_minutes) as overtime_minutes, SUM(late_minutes) as late_minutes, SUM(early_leave_minutes) as early_leave_minutes, SUM(total_break_minutes) as break_minutes')
            ->groupBy('status')
            ->get();

        $stats = [
            'total' => 0,
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'early_leave' => 0,
            'missing_punch' => 0,
            'holiday' => 0,
            'vacation' => 0,
            'weekend' => 0,
            'rest' => 0,
            'unassigned' => 0,
            'work_minutes' => 0,
            'overtime_minutes' => 0,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'break_minutes' => 0,
        ];

        foreach ($rows as $row) {
            $status = $row->status ?: 'unassigned';
            $stats['total'] += (int) $row->count;
            $stats[$status] = (int) $row->count;
            $stats['work_minutes'] += (int) $row->work_minutes;
            $stats['overtime_minutes'] += (int) $row->overtime_minutes;
            $stats['late_minutes'] += (int) $row->late_minutes;
            $stats['early_leave_minutes'] += (int) $row->early_leave_minutes;
            $stats['break_minutes'] += (int) $row->break_minutes;
        }

        return $stats;
    }

    /**
     * Find a summary by its primary key.
     */
    public function findById(int $id): ?DailyAttendanceSummary
    {
        return $this->query()
            ->with($this->defaultWith)
            ->find($id);
    }

    /**
     * Get the (user, date) summary, or null when absent.
     */
    public function findByUserAndDate(int $userId, string $date): ?DailyAttendanceSummary
    {
        return $this->query()
            ->with($this->defaultWith)
            ->forUser($userId)
            ->onDate($date)
            ->first();
    }

    /**
     * Update the supplied summary record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DailyAttendanceSummary $summary, array $data): DailyAttendanceSummary
    {
        $summary->update($data);

        return $summary->fresh($this->defaultWith);
    }

    /**
     * Count summaries matching the supplied filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters = []): int
    {
        return $this->applyFilters($this->query(), $filters)->count();
    }

    /**
     * Apply the supplied filter bag to the supplied query builder.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['user_id'] ?? null, function (Builder $q, $userId): void {
            $q->where('user_id', (int) $userId);
        });

        $query->when($filters['shift_id'] ?? null, function (Builder $q, $shiftId): void {
            $q->where('shift_id', (int) $shiftId);
        });

        $query->when($filters['status'] ?? null, function (Builder $q, $status): void {
            $q->where('status', $status);
        });

        $query->when($filters['session_type'] ?? null, function (Builder $q, $type): void {
            $q->where('session_type', $type);
        });

        $query->when(isset($filters['is_complete']), function (Builder $q) use ($filters): void {
            $q->where('is_complete', (bool) $filters['is_complete']);
        });

        $query->when($filters['date'] ?? null, function (Builder $q, $date): void {
            $q->where('summary_date', $date);
        });

        $query->when($filters['from'] ?? null, function (Builder $q, $from): void {
            $q->where('summary_date', '>=', $from);
        });

        $query->when($filters['to'] ?? null, function (Builder $q, $to): void {
            $q->where('summary_date', '<=', $to);
        });

        $query->when($filters['search'] ?? null, function (Builder $q, $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('notes', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $u) use ($search): void {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%");
                    });
            });
        });

        return $query;
    }
}
