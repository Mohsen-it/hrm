<?php

use Illuminate\Support\Facades\Route;
use Modules\UserActivity\Http\Controllers\UserActivityController;

Route::middleware(['auth', 'permission:view-activity-logs'])
    ->prefix('user-activity')
    ->name('user-activity.')
    ->group(function () {
        Route::get('/', [UserActivityController::class, 'index'])->name('index');
        Route::post('idle-gap', [UserActivityController::class, 'updateIdleGap'])->name('idle-gap');
        Route::get('{user}', [UserActivityController::class, 'show'])->name('show');
    });
