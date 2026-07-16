<?php

namespace Modules\Shifts\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Shifts\Models\ShiftException;
use Modules\Shifts\Repositories\ShiftExceptionRepository;

/**
 * ShiftExceptionService — writes the isolated interceptor rows.
 *
 * Used by the Leave/Swap UI to instantly inject an entry into
 * `att_shift_exceptions` for an exact date range, which the ScheduleResolver
 * consumes in fail-fast order. Does NOT touch the Vacations balance tables.
 */
class ShiftExceptionService
{
    public function __construct(
        private ShiftExceptionRepository $repository,
    ) {}

    /**
     * Create a leave / mission / swap interceptor entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ShiftException
    {
        $data['company_id'] = $data['company_id'] ?? Auth::user()?->company_id;
        $data['created_by'] = $data['created_by'] ?? Auth::id();
        $data['status'] = $data['status'] ?? 'active';

        return $this->repository->create($data);
    }

    /**
     * Cancel (soft via status) an existing interceptor entry.
     */
    public function cancel(int $id): ShiftException
    {
        $exception = $this->repository->query()->findOrFail($id);

        return $this->repository->cancel($exception);
    }

    /**
     * Mirror an approved vacation request into the interceptor table.
     * Idempotent: skips when a row for the same source already exists.
     */
    public function mirrorVacation(
        int $employeeId,
        string $fromDate,
        string $toDate,
        int $vacationRequestId,
        ?int $companyId = null,
    ): ShiftException {
        $existing = $this->repository->query()
            ->where('source', 'vacation')
            ->where('source_id', $vacationRequestId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->repository->create([
            'company_id' => $companyId ?? Auth::user()?->company_id,
            'employee_id' => $employeeId,
            'exception_type' => 'leave',
            'source' => 'vacation',
            'source_id' => $vacationRequestId,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'status' => 'active',
            'reason' => 'Mirrored from approved vacation request #'.$vacationRequestId,
        ]);
    }

    /**
     * Cancel the interceptor row mirrored from a vacation request.
     */
    public function unmirrorVacation(int $vacationRequestId): void
    {
        $this->repository->query()
            ->where('source', 'vacation')
            ->where('source_id', $vacationRequestId)
            ->update(['status' => 'cancelled']);
    }
}
