<?php

use Illuminate\Support\Facades\Route;
use Modules\Subordinations\Http\Controllers\SubordinationsController;

Route::middleware(['auth'])->group(function () {
    Route::middleware('permission:view-subordinations')->group(function () {
        Route::get('subordinations/export/excel', [SubordinationsController::class, 'export'])->name('subordinations.export');
        Route::get('subordinations', [SubordinationsController::class, 'index'])->name('subordinations.index');
        Route::get('subordinations/{subordination}', [SubordinationsController::class, 'show'])->name('subordinations.show');
    });

    Route::middleware('permission:create-subordinations')->group(function () {
        Route::get('subordinations/create', [SubordinationsController::class, 'create'])->name('subordinations.create');
        Route::post('subordinations', [SubordinationsController::class, 'store'])->name('subordinations.store');
    });

    Route::middleware('permission:edit-subordinations')->group(function () {
        Route::get('subordinations/{subordination}/edit', [SubordinationsController::class, 'edit'])->name('subordinations.edit');
        Route::put('subordinations/{subordination}', [SubordinationsController::class, 'update'])->name('subordinations.update');
    });

    Route::middleware('permission:delete-subordinations')->group(function () {
        Route::delete('subordinations/{subordination}', [SubordinationsController::class, 'destroy'])->name('subordinations.destroy');
    });
});
