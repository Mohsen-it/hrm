<?php

namespace Modules\Attendance\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Attendance\Events\SessionCreated;
use Modules\Attendance\Events\SessionDeleted;
use Modules\Attendance\Events\SessionUpdated;
use Modules\Attendance\Listeners\InvalidateAttendanceCache;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        SessionCreated::class => [
            InvalidateAttendanceCache::class.'@handleCreated',
        ],
        SessionUpdated::class => [
            InvalidateAttendanceCache::class.'@handleUpdated',
        ],
        SessionDeleted::class => [
            InvalidateAttendanceCache::class.'@handleDeleted',
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
