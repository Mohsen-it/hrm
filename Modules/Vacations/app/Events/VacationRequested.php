<?php

namespace Modules\Vacations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Vacations\Models\UserVacationRequest;

/**
 * VacationRequested — fired by `VacationRequestService::openRequest()`.
 *
 * Listeners:
 *  - Notify the approver (manager / HR).
 *  - Log the request in the operator's notification feed.
 */
class VacationRequested
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
