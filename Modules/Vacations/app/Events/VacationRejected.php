<?php

namespace Modules\Vacations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Vacations\Models\UserVacationRequest;

/**
 * VacationRejected — fired by `VacationRequestService::rejectRequest()`.
 *
 * Listeners:
 *  - Notify the employee with the manager note (if any).
 *  - Invalidate any attendance summary that the request had reserved
 *    (currently a no-op since reservations live on the balance).
 */
class VacationRejected
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
