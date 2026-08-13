<?php

namespace Modules\UserActivity\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\UserActivity\Services\UserActivityService;

class UserActivityServiceProvider extends ServiceProvider
{
    protected string $name = 'UserActivity';

    /**
     * Bootstrap the module services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        $this->registerTranslations();

        $this->registerEventListeners();
    }

    /**
     * Register the module translations so PHP's `__()` helper resolves
     * `useractivity.*` keys (flash messages etc.). The frontend payload is
     * built separately by HandleInertiaRequests.
     */
    protected function registerTranslations(): void
    {
        $langPath = module_path($this->name, 'lang');

        $this->loadTranslationsFrom($langPath, 'useractivity');
        $this->loadJsonTranslationsFrom($langPath);
    }

    /**
     * Register the module services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(module_path($this->name, 'config/config.php'), 'useractivity');

        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Track sign-in / sign-out events that the request middleware cannot see
     * (the user is not authenticated yet while the login POST is in flight).
     */
    private function registerEventListeners(): void
    {
        Event::listen(Login::class, static function (Login $event): void {
            app(UserActivityService::class)->recordLogin($event->user);
        });

        Event::listen(Logout::class, static function (Logout $event): void {
            app(UserActivityService::class)->recordLogout($event->user);
        });
    }
}
