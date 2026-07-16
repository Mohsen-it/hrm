<?php

use Illuminate\Support\Facades\Route;
use Modules\Holidays\Http\Controllers\HolidaysController;
use Modules\Vacations\Http\Controllers\MyVacationsController;
use Modules\Vacations\Http\Controllers\VacationRequestsController;
use Modules\Vacations\Http\Controllers\VacationTypesController;

/*
| Routes for the Vacations + Holidays modules.
|
| Naming convention:
|   - `vacations.types.*`        : catalog CRUD (HR / admin)
|   - `vacations.requests.*`     : company-wide request queue
|   - `vacations.my.*`           : employee-facing self-service
|   - `holidays.*`               : holiday calendar CRUD + sync
|
| Permission middleware:
|   - view-vacation-types / create-vacation-types / edit-vacation-types / delete-vacation-types
|   - view-vacation-requests / create-vacation-requests / edit-vacation-requests / delete-vacation-requests
|   - approve-vacation-requests                                  : decision endpoints
|   - view-vacations / create-vacations / edit-vacations / delete-vacations
|   - view-holidays / create-holidays / edit-holidays / delete-holidays
*/

Route::middleware(['auth'])->group(function () {
    // ----------------------------------------------------------------
    // Vacation types (catalog)
    // ----------------------------------------------------------------
    Route::middleware('permission:view-vacation-types')->prefix('vacations/types')->name('vacations.types.')->group(function () {
        Route::get('/', [VacationTypesController::class, 'index'])->name('index');
        Route::get('create', [VacationTypesController::class, 'create'])
            ->middleware('permission:create-vacation-types')
            ->name('create');
        Route::post('/', [VacationTypesController::class, 'store'])
            ->middleware('permission:create-vacation-types')
            ->name('store');
        Route::get('{vacationType}', [VacationTypesController::class, 'show'])->name('show');
        Route::get('{vacationType}/edit', [VacationTypesController::class, 'edit'])
            ->middleware('permission:edit-vacation-types')
            ->name('edit');
        Route::put('{vacationType}', [VacationTypesController::class, 'update'])
            ->middleware('permission:edit-vacation-types')
            ->name('update');
        Route::delete('{vacationType}', [VacationTypesController::class, 'destroy'])
            ->middleware('permission:delete-vacation-types')
            ->name('destroy');
    });

    // ----------------------------------------------------------------
    // Vacation requests (HR / manager view)
    // ----------------------------------------------------------------
    Route::middleware('permission:view-vacation-requests')->prefix('vacations/requests')->name('vacations.requests.')->group(function () {
        Route::get('/', [VacationRequestsController::class, 'index'])->name('index');
        Route::get('create', [VacationRequestsController::class, 'create'])
            ->middleware('permission:create-vacation-requests')
            ->name('create');
        Route::post('/', [VacationRequestsController::class, 'store'])
            ->middleware('permission:create-vacation-requests')
            ->name('store');
        Route::get('{vacationRequest}', [VacationRequestsController::class, 'show'])->name('show');
        Route::get('{vacationRequest}/edit', [VacationRequestsController::class, 'edit'])
            ->middleware('permission:edit-vacation-requests')
            ->name('edit');
        Route::put('{vacationRequest}', [VacationRequestsController::class, 'update'])
            ->middleware('permission:edit-vacation-requests')
            ->name('update');
        Route::post('{vacationRequest}/approve', [VacationRequestsController::class, 'approve'])
            ->middleware('permission:approve-vacation-requests')
            ->name('approve');
        Route::post('{vacationRequest}/reject', [VacationRequestsController::class, 'reject'])
            ->middleware('permission:approve-vacation-requests')
            ->name('reject');
        Route::delete('{vacationRequest}', [VacationRequestsController::class, 'destroy'])
            ->middleware('permission:delete-vacation-requests')
            ->name('destroy');
    });

    // ----------------------------------------------------------------
    // My vacations (employee self-service)
    // ----------------------------------------------------------------
    Route::middleware('permission:view-vacations')->prefix('vacations/my')->name('vacations.my.')->group(function () {
        Route::get('/', [MyVacationsController::class, 'index'])->name('index');
        Route::get('create', [MyVacationsController::class, 'create'])
            ->middleware('permission:create-vacations')
            ->name('create');
        Route::post('/', [MyVacationsController::class, 'store'])
            ->middleware('permission:create-vacations')
            ->name('store');
        Route::get('{vacation}', [MyVacationsController::class, 'show'])->name('show');
        Route::get('{vacation}/edit', [MyVacationsController::class, 'edit'])
            ->middleware('permission:edit-vacations')
            ->name('edit');
        Route::put('{vacation}', [MyVacationsController::class, 'update'])
            ->middleware('permission:edit-vacations')
            ->name('update');
        Route::post('{vacation}/cancel', [MyVacationsController::class, 'cancel'])
            ->middleware('permission:delete-vacations')
            ->name('cancel');
    });

    // ----------------------------------------------------------------
    // Holidays
    // ----------------------------------------------------------------
    Route::middleware('permission:view-holidays')->prefix('holidays')->name('holidays.')->group(function () {
        Route::get('/', [HolidaysController::class, 'index'])->name('index');
        Route::get('create', [HolidaysController::class, 'create'])
            ->middleware('permission:create-holidays')
            ->name('create');
        Route::post('/', [HolidaysController::class, 'store'])
            ->middleware('permission:create-holidays')
            ->name('store');
        Route::get('{holiday}', [HolidaysController::class, 'show'])->name('show');
        Route::get('{holiday}/edit', [HolidaysController::class, 'edit'])
            ->middleware('permission:edit-holidays')
            ->name('edit');
        Route::put('{holiday}', [HolidaysController::class, 'update'])
            ->middleware('permission:edit-holidays')
            ->name('update');
        Route::delete('{holiday}', [HolidaysController::class, 'destroy'])
            ->middleware('permission:delete-holidays')
            ->name('destroy');
        Route::post('sync', [HolidaysController::class, 'sync'])
            ->middleware('permission:edit-holidays')
            ->name('sync');
    });
});
