<?php

use Illuminate\Support\Facades\Route;
use Modules\Branches\Http\Controllers\BranchesController;

Route::middleware(['auth', 'permission:view-branches'])
    ->group(function () {
        Route::resource('branches', BranchesController::class)->names('branches');
    });
