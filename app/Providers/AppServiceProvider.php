<?php

namespace App\Providers;

use App\Services\ZKTecoPythonBridgeService;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
