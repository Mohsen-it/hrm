<?php

namespace Modules\Vacations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Vacations\Models\UserVacationRequest;

/**
 * VacationApproved — fired by `VacationRequestService::approveRequest()`.
 *
 * Listeners:
 *  - Update daily attendance summaries for the request's days
 *    (delegated to `VacationIntegrationService`).
 *  - Notify the employee + manager.
 */
class VacationApproved
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
