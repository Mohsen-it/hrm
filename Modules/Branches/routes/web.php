<?php

use Illuminate\Support\Facades\Route;
use Modules\Branches\Http\Controllers\BranchesController;

Route::middleware(['auth', 'permission:view-branches'])
    ->group(function () {
        Route::get('branches/export/excel', [BranchesController::class, 'export'])
            ->name('branches.export');

        Route::resource('branches', BranchesController::class)->names('branches');
    });
