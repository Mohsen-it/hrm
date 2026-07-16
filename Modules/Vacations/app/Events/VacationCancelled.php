<?php

namespace Modules\Vacations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Vacations\Models\UserVacationRequest;

/**
 * VacationCancelled — fired by `VacationRequestService::cancelRequest()`.
 *
 * Listeners:
 *  - Roll back any attendance summary patches the request had applied
 *    (delegated to `VacationIntegrationService`).
 *  - Notify the manager.
 */
class VacationCancelled
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public UserVacationRequest $request,
    ) {}
}
