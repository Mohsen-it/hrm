<?php

use Illuminate\Support\Facades\Route;
use Modules\Positions\Http\Controllers\PositionsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('positions', PositionsController::class)->names('positions');
});
