<?php

namespace Modules\FingerprintDevices\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\FingerprintDevices\Console\Commands\BackfillDeviceOrgDefaults;
use Modules\FingerprintDevices\Console\Commands\ExportFacePhotosToUsb;
use Modules\FingerprintDevices\Console\Commands\FullSyncAllDevices;
use Modules\FingerprintDevices\Console\Commands\DistributeMissingFaceSets;
use Modules\FingerprintDevices\Console\Commands\ImportFacePhotosFromUsb;
use Modules\FingerprintDevices\Console\Commands\ImportHikvisionEmployees;
use Modules\FingerprintDevices\Console\Commands\PushFacesAllDevices;
use Modules\FingerprintDevices\Console\Commands\PullTemplatesDirect;
use Modules\FingerprintDevices\Console\Commands\QueueUsersForAdms;
use Modules\FingerprintDevices\Console\Commands\RetryFailedFaceCommands;
use Modules\FingerprintDevices\Console\Commands\DistributeAllFaces;
use Illuminate\Support\Facades\Schedule;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FingerprintDevicesServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'FingerprintDevices';

    protected string $nameLower = 'fingerprintdevices';

    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        $this->registerObservers();
    }

    protected function registerObservers(): void
    {
        \Modules\Users\Models\User::observe(\Modules\FingerprintDevices\Observers\EmployeeAdmsObserver::class);
    }

    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerCommands(): void
    {
        $this->commands([
            ImportHikvisionEmployees::class,
            BackfillDeviceOrgDefaults::class,
            DistributeMissingFaceSets::class,
            FullSyncAllDevices::class,
            ImportFacePhotosFromUsb::class,
            ExportFacePhotosToUsb::class,
            PushFacesAllDevices::class,
            QueueUsersForAdms::class,
            PullTemplatesDirect::class,
            RetryFailedFaceCommands::class,
            DistributeAllFaces::class,
        ]);
    }

    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function (): void {
            // Automatically retry failed face-template commands every 5 minutes.
            // iFace 880 Plus devices intermittently return -3 on face writes;
            // periodic retries recover transient failures without operator
            // intervention.  Using 5 minutes (instead of 10) speeds up recovery
            // for large deployments with thousands of face commands.
            Schedule::command('fingerprints:retry-failed-faces --limit=200')
                ->everyFiveMinutes()
                ->withoutOverlapping();

            // Distribute complete face-template enrollment sets to devices
            // that are missing them. Runs every 30 minutes.
            Schedule::command('fingerprints:distribute-missing-faces')
                ->everyThirtyMinutes()
                ->withoutOverlapping();

            // Distribute ALL face templates (complete and partial) to all devices.
            // Runs every 60 minutes to catch any newly enrolled faces.
            Schedule::command('fingerprints:distribute-all-faces')
                ->hourly()
                ->withoutOverlapping();
        });
    }

    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    protected function registerConfig(): void
    {
        $relativeConfigPath = config('modules.paths.generator.config.path');
        $configPath = module_path($this->name, $relativeConfigPath);

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relativePath = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $configKey = $this->nameLower.'.'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);
                    $key = ($relativePath === 'config.php') ? $this->nameLower : $configKey;

                    $this->publishes([$file->getPathname() => config_path($relativePath)], 'config');
                    $this->mergeConfigFrom($file->getPathname(), $key);
                }
            }
        }
    }

    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        $componentNamespace = $this->module_namespace($this->name, $this->app_path(config('modules.paths.generator.component-class.path')));
        Blade::componentNamespace($componentNamespace, $this->nameLower);
    }

    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->nameLower)) {
                $paths[] = $path.'/modules/'.$this->nameLower;
            }
        }

        return $paths;
    }
}
