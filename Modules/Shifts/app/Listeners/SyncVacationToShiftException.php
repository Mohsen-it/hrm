<?php

namespace Modules\Shifts\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Shifts\Services\ShiftExceptionService;
use Modules\Vacations\Events\VacationApproved;
use Modules\Vacations\Events\VacationCancelled;

/**
 * SyncVacationToShiftException — bridges approved vacation requests into the
 * isolated `att_shift_exceptions` interceptor table.
 *
 * This keeps the ScheduleResolver decoupled from the Vacations module at query
 * time: it has a single denormalised lookup. The listener is additive and
 * failure-tolerant — a vacation lifecycle error never rolls back attendance
 * integration (handled by the Vacations module's own listener).
 */
class SyncVacationToShiftException implements ShouldQueue
{
    public string $queue = 'attendance';

    public function __construct(
        private ShiftExceptionService $exceptionService,
    ) {}

    public function handleApproved(VacationApproved $event): void
    {
        $request = $event->request;

        $this->exceptionService->mirrorVacation(
            employeeId: $request->user_id,
            fromDate: $request->start_date->toDateString(),
            toDate: $request->end_date->toDateString(),
            vacationRequestId: $request->id,
            companyId: $request->user?->company_id,
        );
    }

    public function handleCancelled(VacationCancelled $event): void
    {
        $this->exceptionService->unmirrorVacation($event->request->id);
    }
}
