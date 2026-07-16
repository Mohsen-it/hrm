<?php

use Illuminate\Support\Facades\Route;
use Modules\Shifts\Http\Controllers\ScheduleResolverController;
use Modules\Shifts\Http\Controllers\ShiftExceptionController;
use Modules\Shifts\Http\Controllers\ShiftsController;

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
    Route::apiResource('shifts', ShiftsController::class)->names('shifts');

    // Dynamic Shift Engine — Virtual Roster resolver (Step 4.1) + reports (Step 5).
    Route::get('shifts/resolve/employee/{id}', [ScheduleResolverController::class, 'employee']);
    Route::get('shifts/resolve/department/{departmentId}', [ScheduleResolverController::class, 'department']);
    Route::get('shifts/resolve/day/{id}', [ScheduleResolverController::class, 'day']);

    // Instant Leave & Shift-Swap interception (Step 4.4).
    Route::get('shifts/exceptions', [ShiftExceptionController::class, 'index']);
    Route::post('shifts/exceptions', [ShiftExceptionController::class, 'store']);
    Route::delete('shifts/exceptions/{id}', [ShiftExceptionController::class, 'destroy']);
});
