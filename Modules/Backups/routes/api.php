<?php

use Illuminate\Support\Facades\Route;
use Modules\Backups\Http\Controllers\BackupsController;

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
    // NOTE: BackupsController is an Inertia (web) controller. Only expose
    // the actions that actually exist — `update` does not, so it is
    // excluded to avoid a 500 on PUT/PATCH (405 instead).
    Route::apiResource('backups', BackupsController::class)->only(['index', 'store', 'show', 'destroy'])->names('backups');
});
