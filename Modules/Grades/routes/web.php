<?php

use Illuminate\Support\Facades\Route;
use Modules\Grades\Http\Controllers\GradesController;

Route::middleware(['auth', 'permission:view-grades'])
    ->group(function () {
        Route::resource('grades', GradesController::class)->names('grades');
    });
