<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

trait PaginatesResults
{
    /**
     * Paginate results or return all if per_page is "all".
     */
    protected function paginateOrAll(
        Builder $query,
        int|string $perPage = 20,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        if ($perPage === 'all' || $perPage === -1) {
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
