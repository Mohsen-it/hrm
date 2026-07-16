<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\UsersController;

Route::middleware(['auth', 'permission:view-users'])
    ->group(function () {
        // Bulk operations
        Route::post('users/bulk-delete', [UsersController::class, 'bulkDelete'])
            ->name('users.bulk-delete');

        // Sub-resources
        Route::prefix('users/{user}')->name('users.')->group(function () {
            Route::get('shifts', [UsersController::class, 'shifts'])
                ->name('shifts');
            Route::post('shifts', [UsersController::class, 'updateShifts'])
                ->name('shifts.update');

            Route::get('fingerprints', [UsersController::class, 'fingerprints'])
                ->name('fingerprints');

            Route::post('roles', [UsersController::class, 'updateRoles'])
                ->name('roles.update');
            Route::post('permissions', [UsersController::class, 'updatePermissions'])
                ->name('permissions.update');
        });

        // Standard CRUD
        Route::resource('users', UsersController::class)->names('users');
    });
