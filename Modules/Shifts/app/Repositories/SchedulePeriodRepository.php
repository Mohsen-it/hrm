<?php

namespace Modules\Shifts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Shifts\Models\SchedulePeriod;

class SchedulePeriodRepository
{
    /**
     * Get all schedule periods with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = SchedulePeriod::query()
            ->with(['generatedBy', 'publishedBy']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        if (! empty($filters['month'])) {
            $query->where('month', $filters['month']);
        }

        $sortBy = $filters['sort_by'] ?? 'year';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    /**
     * Find a schedule period by its primary key.
     */
    public function findById(int $id): ?SchedulePeriod
    {
        return SchedulePeriod::with(['generatedBy', 'publishedBy'])->find($id);
    }

    /**
     * Find draft period for a given year/month.
     */
    public function findDraft(int $year, int $month): ?SchedulePeriod
    {
        return SchedulePeriod::where('year', $year)
            ->where('month', $month)
            ->where('status', 'draft')
            ->first();
    }
}
