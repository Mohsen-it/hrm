<?php

use Illuminate\Support\Facades\Route;
use Modules\Grades\Http\Controllers\GradesController;

Route::middleware(['auth', 'permission:view-grades'])
    ->group(function () {
        Route::get('grades/export/excel', [GradesController::class, 'export'])
            ->name('grades.export');

        Route::resource('grades', GradesController::class)->names('grades');
    });
