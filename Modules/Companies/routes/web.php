<?php

use Illuminate\Support\Facades\Route;
use Modules\Companies\Http\Controllers\CompaniesController;

Route::middleware(['auth', 'permission:view-companies'])
    ->group(function () {
        Route::get('companies/export/excel', [CompaniesController::class, 'export'])
            ->name('companies.export');

        Route::get('companies/search', [CompaniesController::class, 'search'])
            ->name('companies.search');

        Route::resource('companies', CompaniesController::class)->names('companies');
    });
