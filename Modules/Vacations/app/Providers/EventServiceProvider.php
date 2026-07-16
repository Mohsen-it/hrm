<?php

namespace Modules\Vacations\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Vacations\Events\VacationApproved;
use Modules\Vacations\Events\VacationCancelled;
use Modules\Vacations\Events\VacationRejected;
use Modules\Vacations\Events\VacationRequested;
use Modules\Vacations\Listeners\SendVacationNotifications;
use Modules\Vacations\Listeners\UpdateAttendanceOnVacationDecision;

/**
 * EventServiceProvider — wires the four vacation lifecycle events to
 * the notification + integration listeners.
 *
 * The notification fan-out runs first; the integration listener runs
 * after so the database reflects the new status before the email is
 * sent. Both listeners are queueable (`ShouldQueue`) so a flood of
 * approvals does not block the HTTP request.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        VacationRequested::class => [
            SendVacationNotifications::class.'@handleRequested',
        ],
        VacationApproved::class => [
            SendVacationNotifications::class.'@handleApproved',
            UpdateAttendanceOnVacationDecision::class.'@handleApproved',
        ],
        VacationRejected::class => [
            SendVacationNotifications::class.'@handleRejected',
            UpdateAttendanceOnVacationDecision::class.'@handleRejected',
        ],
        VacationCancelled::class => [
            UpdateAttendanceOnVacationDecision::class.'@handleCancelled',
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void
    {
        //
    }
}
