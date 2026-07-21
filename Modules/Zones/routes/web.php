<?php

use Illuminate\Support\Facades\Route;
use Modules\Zones\Http\Controllers\ZoneBranchesController;
use Modules\Zones\Http\Controllers\ZonesController;

Route::middleware(['auth', 'permission:view-zones'])->group(function () {
    Route::get('zones/export/excel', [ZonesController::class, 'export'])
        ->name('zones.export');

    Route::get('zones/dashboard', [ZonesController::class, 'dashboard'])
        ->name('zones.dashboard');

    Route::resource('zones', ZonesController::class)
        ->parameters(['zones' => 'zone'])
        ->except(['create', 'edit'])
        ->names([
            'index' => 'zones.index',
            'store' => 'zones.store',
            'show' => 'zones.show',
            'update' => 'zones.update',
            'destroy' => 'zones.destroy',
        ]);

    Route::get('zones/{zone}/branches', [ZonesController::class, 'branches'])
        ->name('zones.branches');

    Route::post('zones/{zone}/branches/assign', [ZonesController::class, 'assignBranches'])
        ->name('zones.branches.assign');

    Route::get('zones/{zone}/branches/list', [ZoneBranchesController::class, 'index'])
        ->name('zones.branches.list');

    Route::post('zones/{zone}/branches', [ZoneBranchesController::class, 'store'])
        ->name('zones.branches.attach');

    Route::delete('zones/{zone}/branches/{branch}', [ZoneBranchesController::class, 'destroy'])
        ->name('zones.branches.detach');

    Route::post('zones/{zone}/recalculate', [ZonesController::class, 'recalculate'])
        ->name('zones.recalculate');

    Route::post('zones/{zone}/sync-devices', [ZonesController::class, 'syncDevices'])
        ->name('zones.sync-devices');
});
