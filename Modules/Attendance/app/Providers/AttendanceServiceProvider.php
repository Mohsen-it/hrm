<?php

namespace Modules\Attendance\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Attendance\Console\Commands\CleanupOldLogsCommand;
use Modules\Attendance\Console\Commands\DetectAnomaliesCommand;
use Modules\Attendance\Console\Commands\ExportDailyReportCommand;
use Modules\Attendance\Console\Commands\ExportMonthlyReportCommand;
use Modules\Attendance\Console\Commands\GenerateDailySummariesCommand;
use Modules\Attendance\Console\Commands\MarkAbsentCommand;
use Modules\Attendance\Console\Commands\MarkLateArrivalsCommand;
use Modules\Attendance\Console\Commands\ProcessRawLogCommand;
use Modules\Attendance\Console\Commands\ProcessRawLogsCommand;
use Modules\Attendance\Console\Commands\RecalculateDateCommand;
use Modules\Attendance\Console\Commands\RecalculateRangeCommand;
use Modules\Attendance\Console\Commands\RecalculateSummariesCommand;
use Modules\Attendance\Console\Commands\RecalculateUserCommand;
use Modules\Attendance\Console\Commands\SendDailyDigestCommand;
use Modules\Attendance\Console\Commands\SendWeeklyDigestCommand;
use Modules\Attendance\Console\Commands\SyncFingerprintsCommand;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AttendanceServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Attendance';

    protected string $nameLower = 'attendance';

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
            RecalculateSummariesCommand::class,
            RecalculateDateCommand::class,
            RecalculateRangeCommand::class,
            RecalculateUserCommand::class,
            ProcessRawLogsCommand::class,
            ProcessRawLogCommand::class,
            MarkAbsentCommand::class,
            GenerateDailySummariesCommand::class,
            MarkLateArrivalsCommand::class,
            DetectAnomaliesCommand::class,
            CleanupOldLogsCommand::class,
            ExportDailyReportCommand::class,
            ExportMonthlyReportCommand::class,
            SendDailyDigestCommand::class,
            SendWeeklyDigestCommand::class,
            SyncFingerprintsCommand::class,
        ]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function (): void {
            // Sync attendance from fingerprint devices every 5 minutes
            Schedule::command('attendance:sync-fingerprints')->everyFiveMinutes()->withoutOverlapping();

            // Process unprocessed raw logs into sessions every 2 minutes
            Schedule::command('attendance:process-raw-logs')->everyTwoMinutes()->withoutOverlapping();

            // Mark absent employees at 10:00 AM daily
            Schedule::command('attendance:mark-absent')->dailyAt('10:00')->withoutOverlapping();

            // Generate daily summaries at 11:00 PM daily
            Schedule::command('attendance:generate-daily-summaries')->dailyAt('23:00')->withoutOverlapping();

            // Send daily digest at 6:00 PM daily
            Schedule::command('attendance:send-daily-digest')->dailyAt('18:00')->withoutOverlapping();

            // Send weekly digest every Sunday at 8:00 PM
            Schedule::command('attendance:send-weekly-digest')->weeklyOn(0, '20:00')->withoutOverlapping();

            // Cleanup old raw logs weekly (Sunday at 3:00 AM)
            Schedule::command('attendance:cleanup-old-logs')->weeklyOn(0, '03:00')->withoutOverlapping();
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
     * Get the services provided by the service provider.
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
