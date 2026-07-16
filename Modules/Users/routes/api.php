<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\UsersController;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('users/bulk-delete', [UsersController::class, 'bulkDelete'])
        ->name('users.bulk-delete');

    Route::prefix('users/{user}')->name('users.')->group(function () {
        Route::post('shifts', [UsersController::class, 'updateShifts'])
            ->name('shifts.update');
        Route::post('roles', [UsersController::class, 'updateRoles'])
            ->name('roles.update');
        Route::post('permissions', [UsersController::class, 'updatePermissions'])
            ->name('permissions.update');
    });

    Route::apiResource('users', UsersController::class)->names('users');
});
