<?php

use Illuminate\Support\Facades\Route;
use Modules\Backups\Http\Controllers\BackupsController;

/*
|--------------------------------------------------------------------------
| Backups Web Routes (plan §18 — permission-gated)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::get('backups', [BackupsController::class, 'index'])
        ->middleware('permission:view-backups')
        ->name('backups.index');

    Route::get('backups/settings', [BackupsController::class, 'settings'])
        ->middleware('permission:manage-backup-settings')
        ->name('backups.settings');

    Route::post('backups/settings', [BackupsController::class, 'updateSettings'])
        ->middleware('permission:manage-backup-settings')
        ->name('backups.settings.update');

    Route::post('backups', [BackupsController::class, 'store'])
        ->middleware('permission:create-backups')
        ->name('backups.store');

    Route::get('backups/{id}', [BackupsController::class, 'show'])
        ->middleware('permission:view-backups')
        ->whereNumber('id')
        ->name('backups.show');

    Route::post('backups/{id}/verify', [BackupsController::class, 'verify'])
        ->middleware('permission:verify-backups')
        ->whereNumber('id')
        ->name('backups.verify');

    Route::get('backups/{id}/download', [BackupsController::class, 'download'])
        ->middleware('permission:download-backups')
        ->whereNumber('id')
        ->name('backups.download');

    Route::post('backups/{id}/restore-test', [BackupsController::class, 'restoreTest'])
        ->middleware('permission:restore-backups')
        ->whereNumber('id')
        ->name('backups.restore-test');

    Route::post('backups/{id}/restore', [BackupsController::class, 'restoreProduction'])
        ->middleware('permission:restore-backups-production')
        ->whereNumber('id')
        ->name('backups.restore');

    Route::delete('backups/{id}', [BackupsController::class, 'destroy'])
        ->middleware('permission:delete-backups')
        ->whereNumber('id')
        ->name('backups.destroy');
});
