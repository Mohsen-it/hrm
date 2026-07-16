<?php

namespace Modules\Shifts\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Shifts\Listeners\SyncVacationToShiftException;
use Modules\Vacations\Events\VacationApproved;
use Modules\Vacations\Events\VacationCancelled;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        VacationApproved::class => [
            SyncVacationToShiftException::class,
        ],
        VacationCancelled::class => [
            SyncVacationToShiftException::class,
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
