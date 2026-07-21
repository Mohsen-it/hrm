<?php

use Illuminate\Support\Facades\Route;
use Modules\Positions\Http\Controllers\PositionsController;

Route::middleware(['auth', 'permission:view-positions'])
    ->group(function () {
        Route::get('positions/export/excel', [PositionsController::class, 'export'])
            ->name('positions.export');

        Route::resource('positions', PositionsController::class)->names('positions');
    });
