<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\VacationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (no auth required)
|--------------------------------------------------------------------------
*/

Route::middleware('set.locale')->group(function () {
    // Auth
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Language switch
    Route::get('/language/{locale}', [LanguageController::class, 'switchLanguage'])
        ->name('language.switch')
        ->where('locale', '[a-z]{2}');
});

/*
|--------------------------------------------------------------------------
| Protected Routes (auth required)
|--------------------------------------------------------------------------
*/

Route::middleware(['set.locale', 'auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/snapshot', [DashboardController::class, 'snapshot'])
        ->middleware('throttle:30,1')
        ->name('dashboard.snapshot');
    Route::get('/dashboard/pull-events', [DashboardController::class, 'pullEvents'])
        ->middleware('throttle:12,1')
        ->name('dashboard.pullEvents');

    // Roles
    Route::resource('roles', RoleController::class)
        ->parameters(['roles' => 'role'])
        ->except(['show', 'create', 'edit'])
        ->names('roles');

    // Permissions
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::post('permissions/attach', [PermissionController::class, 'attach'])->name('permissions.attach');
    Route::post('permissions/detach', [PermissionController::class, 'detach'])->name('permissions.detach');

    // Cross-cutting vacation overview
    Route::get('vacations/dashboard', [VacationController::class, 'index'])
        ->name('vacations.dashboard');

    // Root redirect
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });
});
