<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\SettingsController;

Route::middleware(['auth'])->group(function () {
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('general', [SettingsController::class, 'general'])->name('general');
        Route::get('attendance', [SettingsController::class, 'attendance'])->name('attendance');

        Route::post('bulk-update', [SettingsController::class, 'bulkUpdate'])->name('bulk-update');
        Route::post('{setting}/flush-cache', [SettingsController::class, 'flushCache'])->name('flush-cache');
        Route::delete('{setting}', [SettingsController::class, 'destroy'])->name('destroy');

        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::post('/', [SettingsController::class, 'store'])->name('store');
        Route::put('{setting}', [SettingsController::class, 'update'])->name('update');
    });
});
