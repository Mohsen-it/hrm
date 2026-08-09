<?php

namespace Modules\Vacations\Repositories;

use App\Traits\PaginatesResults;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Vacations\Models\AttendanceJustificationRequest;

class AttendanceJustificationRequestRepository
{
    use PaginatesResults;

    /** Paginate requests with the names required by the UI, avoiding N+1 queries. */
    public function paginate(array $filters, int|string $perPage = 20): LengthAwarePaginator
    {
        $query = AttendanceJustificationRequest::query()->with('user:id,name,employee_code')
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('employee_code', 'like', "%{$term}%")))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', $date))
            ->latest('requested_at');

        return $this->paginateOrAll($query, $perPage);
    }

    /** Persist a prepared request. */
    public function create(array $data): AttendanceJustificationRequest
    {
        return AttendanceJustificationRequest::create($data);
    }

    /** Find one request with its employee for the edit screen. */
    public function find(int $id): ?AttendanceJustificationRequest
    {
        return AttendanceJustificationRequest::query()->with('user:id,name,employee_code')->find($id);
    }

    /** Update an existing request. */
    public function update(AttendanceJustificationRequest $request, array $data): AttendanceJustificationRequest
    {
        $request->update($data);

        return $request->fresh('user:id,name,employee_code');
    }

    /** Soft-delete an incorrect request. */
    public function delete(AttendanceJustificationRequest $request): bool
    {
        return $request->delete();
    }

    /** Retrieve all matching rows for a bounded export. */
    public function export(array $filters): Collection
    {
        return $this->paginate($filters, 'all')->getCollection();
    }
}
