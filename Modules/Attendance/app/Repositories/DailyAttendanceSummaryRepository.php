<?php

namespace Modules\Attendance\Repositories;

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
    /**
     * Default eager-loaded relations to prevent N+1 when listing summaries.
     *
     * @var array<int, string>
     */
    protected array $defaultWith = [
        'user',
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
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->query()->with($this->defaultWith),
            $filters
        )
            ->latest('summary_date')
            ->paginate($perPage);
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
            $q->where('notes', 'like', "%{$search}%");
        });

        return $query;
    }
}
