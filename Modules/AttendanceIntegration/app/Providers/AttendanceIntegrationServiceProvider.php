<?php

namespace Modules\AttendanceIntegration\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\Events\DeviceSyncCompleted;
use Modules\AttendanceIntegration\Events\PunchReceived;
use Modules\AttendanceIntegration\Http\Middleware\AuthenticateDevice;
use Modules\AttendanceIntegration\Http\Middleware\LogDeviceRequest;
use Modules\AttendanceIntegration\Listeners\PublishLivePunchEvent;
use Modules\AttendanceIntegration\Listeners\UpdateDeviceSyncTimestamp;
use Modules\AttendanceIntegration\Repositories\DeviceRepository;
use Modules\AttendanceIntegration\Services\AuditLogger;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Modules\AttendanceIntegration\Services\DeviceSyncOrchestrator;
use Modules\AttendanceIntegration\Services\LivePunchFeedService;
use Modules\AttendanceIntegration\Services\PunchIngestionService;

class AttendanceIntegrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerConfig();
        $this->registerRoutes();
        $this->registerEvents();
        $this->registerLogChannels();
        $this->registerMiddleware();
        $this->registerMigrations();
        $this->registerRateLimiters();
    }

    public function register(): void
    {
        $this->app->singleton(DeviceAdapterResolver::class);
        $this->app->singleton(DeviceRepositoryInterface::class, DeviceRepository::class);
        $this->app->singleton(LivePunchFeedService::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(PunchIngestionService::class);
        $this->app->singleton(DeviceSyncOrchestrator::class);
    }

    private function registerCommands(): void
    {
        //
    }

    private function registerConfig(): void
    {
        $this->mergeConfigFrom(module_path('AttendanceIntegration', '/config/config.php'), 'attendanceintegration');
        $this->publishes([
            module_path('AttendanceIntegration', '/config/config.php') => config_path('attendanceintegration.php'),
        ], 'config');
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(module_path('AttendanceIntegration', '/routes/api.php'));
        $this->loadRoutesFrom(module_path('AttendanceIntegration', '/routes/channels.php'));
    }

    private function registerEvents(): void
    {
        Event::listen(
            PunchReceived::class,
            PublishLivePunchEvent::class,
        );

        Event::listen(
            DeviceSyncCompleted::class,
            UpdateDeviceSyncTimestamp::class,
        );
    }

    private function registerLogChannels(): void
    {
        $channels = require module_path('AttendanceIntegration', '/app/Logs/channels.php');

        $existing = $this->app['config']->get('logging.channels', []);
        $merged = array_merge($existing, $channels);
        $this->app['config']->set('logging.channels', $merged);
    }

    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('device.auth', AuthenticateDevice::class);
        $router->aliasMiddleware('device.log', LogDeviceRequest::class);
    }

    private function registerMigrations(): void
    {
        $this->loadMigrationsFrom(module_path('AttendanceIntegration', '/database/migrations'));
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('attendance_push', function (Request $request) {
            $key = 'attendance_push:'.($request->input('SN') ?? $request->ip());

            return Limit::perMinute(
                (int) config('attendanceintegration.push.rate_limit', 60)
            )->by($key);
        });
    }
}
