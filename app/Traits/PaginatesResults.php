<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

trait PaginatesResults
{
    /**
     * Paginate results or return all if per_page is "all".
     *
     * Guard: for large tables `per_page=all` is capped to 1000 rows to prevent OOM.
     * Callers needing full export should use chunk/cursor or dedicated export jobs.
     */
    protected function paginateOrAll(
        Builder $query,
        int|string $perPage = 20,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        if ($perPage === 'all' || $perPage === -1) {
            $largeTables = ['attendance_sessions', 'raw_attendance_logs', 'daily_attendance_summaries', 'iclock_transaction', 'device_sync_logs'];
            $table = $query->getModel()->getTable();

            if (in_array($table, $largeTables, true)) {
                // Cap large tables: treat `all` as first 1000 ordered rows, still paginated as single page
                $items = $query->limit(1000)->get();
                $total = $items->count();

                return new LengthAwarePaginator(
                    $items,
                    $total,
                    $total > 0 ? $total : 1,
                    1,
                    [
                        'path' => Paginator::resolveCurrentPath(),
                        'pageName' => $pageName,
                    ]
                );
            }

            $items = $query->get();
            $total = $items->count();
            $currentPage = 1;

            return new LengthAwarePaginator(
                $items,
                $total,
                $total,
                $currentPage,
                [
                    'path' => Paginator::resolveCurrentPath(),
                    'pageName' => $pageName,
                ]
            );
        }

        return $query->paginate((int) $perPage, ['*'], $pageName);
    }
}
