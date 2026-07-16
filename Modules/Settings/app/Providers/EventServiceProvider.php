<?php

namespace Modules\Settings\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [];

    protected static $shouldDiscoverEvents = true;

    protected function configureEmailVerification(): void
    {
        //
    }
}
