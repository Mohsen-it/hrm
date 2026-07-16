<?php

namespace Modules\Vacations\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Modules\Vacations\Events\VacationApproved;
use Modules\Vacations\Events\VacationCancelled;
use Modules\Vacations\Events\VacationRejected;
use Modules\Vacations\Services\VacationIntegrationService;

/**
 * UpdateAttendanceOnVacationDecision — bridge the Vacations module to the
 * Attendance module.
 *
 * For an `approved` request the listener delegates to
 * `VacationIntegrationService::markDatesAsVacation()`. For a `cancelled`
 * request the listener calls `markDatesBackToDefault()`. Rejected
 * requests are a no-op (the balance was refunded; no summary was
 * patched).
 *
 * Failures are swallowed into the log so the vacation lifecycle does
 * not roll back on a transient integration error — a separate
 * reconciliation job can sweep any drift.
 */
class UpdateAttendanceOnVacationDecision implements ShouldQueue
{
    /**
     * Queue connection / tube for the listener.
     */
    public string $queue = 'attendance';

    /**
     * Number of seconds the listener may run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new listener instance.
     */
    public function __construct(
        private VacationIntegrationService $integration,
    ) {}

    /**
     * Handle the `VacationApproved` event.
     */
    public function handleApproved(VacationApproved $event): void
    {
        try {
            $this->integration->markDatesAsVacation($event->request);
        } catch (\Throwable $e) {
            Log::warning('VacationApproved integration failed', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the `VacationCancelled` event.
     */
    public function handleCancelled(VacationCancelled $event): void
    {
        try {
            $this->integration->markDatesBackToDefault($event->request);
        } catch (\Throwable $e) {
            Log::warning('VacationCancelled integration failed', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the `VacationRejected` event (no-op).
     */
    public function handleRejected(VacationRejected $event): void
    {
        // No summary was patched, so nothing to roll back.
    }
}
