<?php

use Illuminate\Support\Facades\Route;
use Modules\Positions\Http\Controllers\PositionsController;

Route::middleware(['auth', 'permission:view-positions'])
    ->group(function () {
        Route::resource('positions', PositionsController::class)->names('positions');
    });
