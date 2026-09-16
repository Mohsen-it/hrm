<?php

namespace Modules\Backups\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Backups\Console\Commands\BackupCleanupCommand;
use Modules\Backups\Console\Commands\BackupCreateCommand;
use Modules\Backups\Console\Commands\BackupHealthCheckCommand;
use Modules\Backups\Console\Commands\BackupListCommand;
use Modules\Backups\Console\Commands\BackupRestoreCommand;
use Modules\Backups\Console\Commands\BackupRestoreTestCommand;
use Modules\Backups\Console\Commands\BackupStatusCommand;
use Modules\Backups\Console\Commands\BackupVerifyCommand;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class BackupsServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Backups';

    protected string $nameLower = 'backups';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $this->commands([
            BackupCreateCommand::class,
            BackupListCommand::class,
            BackupStatusCommand::class,
            BackupVerifyCommand::class,
            BackupRestoreTestCommand::class,
            BackupRestoreCommand::class,
            BackupCleanupCommand::class,
            BackupHealthCheckCommand::class,
        ]);
    }

    /**
     * Register command Schedules.
     *
     * Daily 02:00, weekly Sun 03:00, monthly 1st 04:00, restore-test
     * Sun 06:00, cleanup after verification. All guarded by
     * withoutOverlapping + onOneServer (plan §12).
     */
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function () {
            // Read live schedule times from DB so admin changes apply immediately.
            // Gracefully skip if the backup_configs table does not exist
            // (e.g. unit tests using RefreshDatabase without this module's migrations).
            $dailyTime = '02:00';
            $tz = (string) config('backups.timezone', 'Asia/Damascus');
            $weekDay = 0;
            try {
                /** @var \Modules\Backups\Models\BackupConfig|null $dbConfig */
                $dbConfig = \Modules\Backups\Models\BackupConfig::query()->first();
                if ($dbConfig) {
                    $dailyTime = (string) ($dbConfig->scheduled_time ?: '02:00');
                    $tz = (string) ($dbConfig->timezone ?: config('backups.timezone', 'Asia/Damascus'));
                    $weekDay = (int) ($dbConfig->day_of_week ?? 0);
                }
            } catch (\Throwable $e) {
                // Table not present — fall back to env/config defaults.
            }

            if (! (bool) config('backups.enabled', true)) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);

            $schedule->command('backup:create')
                ->dailyAt($dailyTime)
                ->timezone($tz)
                ->withoutOverlapping()
                ->onOneServer()
                ->runInBackground();

            $schedule->command('backup:restore-test')
                ->weeklyOn($weekDay, '06:00')
                ->timezone($tz)
                ->withoutOverlapping()
                ->onOneServer()
                ->runInBackground();

            $schedule->command('backup:cleanup')
                ->dailyAt('02:30')
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command('backup:health-check')
                ->dailyAt('02:45')
                ->withoutOverlapping()
                ->onOneServer();
        });
    }

    /**
     * Register translations.
     */
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

    /**
     * Register config.
     */
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

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        $componentNamespace = $this->module_namespace($this->name, $this->app_path(config('modules.paths.generator.component-class.path')));
        Blade::componentNamespace($componentNamespace, $this->nameLower);
    }

    /**
     * Get the services provided by the provider.
     */
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
