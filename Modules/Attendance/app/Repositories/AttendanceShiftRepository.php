<?php

namespace Modules\Attendance\Repositories;

use App\Traits\PaginatesResults;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Attendance\Models\AttendanceShift;

/**
 * Repository for AttendanceShift CRUD operations.
 */
class AttendanceShiftRepository
{
    use PaginatesResults;

    protected array $defaultWith = ['company', 'details'];

    public function query(): Builder
    {
        return AttendanceShift::query();
    }

    public function getAll(array $filters = [], int|string $perPage = 20): LengthAwarePaginator
    {
        return $this->paginateOrAll(
            $this->applyFilters($this->query()->with($this->defaultWith), $filters)->latest(),
            $perPage
        );
    }

    public function findById(int $id): ?AttendanceShift
    {
        return $this->query()->with([...$this->defaultWith, 'details.timeInterval'])->find($id);
    }

    public function create(array $data): AttendanceShift
    {
        return AttendanceShift::create($data);
    }

    public function update(AttendanceShift $shift, array $data): AttendanceShift
    {
        $shift->update($data);

        return $shift->fresh($this->defaultWith);
    }

    public function delete(AttendanceShift $shift): bool
    {
        return $shift->delete();
    }

    public function getByCompany(int $companyId): Collection
    {
        return $this->query()
            ->with('details')
            ->where('company_id', $companyId)
            ->active()
            ->get();
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['company_id'] ?? null, fn (Builder $q, $val) => $q->where('company_id', (int) $val));
        $query->when($filters['search'] ?? null, function (Builder $q, $search) {
            $q->where('alias', 'like', "%{$search}%");
        });

        return $query;
    }
}
