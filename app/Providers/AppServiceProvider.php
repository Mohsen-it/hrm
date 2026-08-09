<?php

namespace App\Providers;

use App\Services\ExcelExportService;
use App\Services\ZKTecoPythonBridgeService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Facades\Module;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ZKTecoPythonBridgeService::class, function () {
            return new ZKTecoPythonBridgeService;
        });

        $this->app->singleton(ExcelExportService::class, function () {
            return new ExcelExportService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        $this->registerModuleTranslationPaths();
    }

    /**
     * Make each enabled module's lang directory resolvable for plain `__()`
     * calls (e.g. `__('shifts.employee_rotation_assignment_conflict')`).
     *
     * Module lang files live in `Modules/{Name}/lang/{locale}/{module}.php`, but
     * the Laravel translator only resolves two-segment keys against its default
     * paths, so without this every server-side module message falls back to its
     * raw key. The paths are appended after the application lang directory, so
     * root-level files (menu, common, ...) keep their precedence.
     */
    private function registerModuleTranslationPaths(): void
    {
        $this->app->booted(function (): void {
            $translator = app('translator');

            foreach (Module::allEnabled() as $module) {
                $langPath = $module->getPath().'/lang';

                if (is_dir($langPath)) {
                    $translator->addPath($langPath);
                }
            }
        });
    }
}
