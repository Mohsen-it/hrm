<?php

use Illuminate\Support\Facades\Route;
use Modules\Departments\Http\Controllers\DepartmentsController;

Route::middleware(['auth', 'permission:view-departments'])
    ->group(function () {
        Route::get('departments/export/excel', [DepartmentsController::class, 'export'])
            ->name('departments.export');

        Route::resource('departments', DepartmentsController::class)->names('departments');
    });
