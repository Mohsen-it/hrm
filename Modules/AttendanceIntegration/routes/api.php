<?php

use Illuminate\Support\Facades\Route;
use Modules\AttendanceIntegration\Http\Controllers\BiodataController;
use Modules\AttendanceIntegration\Http\Controllers\DevicePushController;
use Modules\AttendanceIntegration\Http\Controllers\LivePunchFeedController;
use Modules\AttendanceIntegration\Http\Controllers\UserpicController;
use Modules\AttendanceIntegration\Http\Middleware\LogDeviceRequest;
use Modules\FingerprintDevices\Http\Controllers\AdmsCommandController;

Route::prefix('api/attendance-integration')->group(function () {
    Route::post('push', [DevicePushController::class, 'handle'])
        ->middleware([LogDeviceRequest::class, 'throttle:attendance_push'])
        ->name('attendance-integration.push');

    Route::match(['get', 'post'], 'push/adms', [DevicePushController::class, 'handle'])
        ->middleware([LogDeviceRequest::class, 'throttle:attendance_push'])
        ->name('attendance-integration.push.adms');

    Route::match(['get', 'post'], 'push/biodata', [BiodataController::class, 'handle'])
        ->middleware([LogDeviceRequest::class, 'throttle:attendance_push'])
        ->name('attendance-integration.push.biodata');

    Route::match(['get', 'post'], 'push/userpic', [UserpicController::class, 'handle'])
        ->middleware([LogDeviceRequest::class, 'throttle:attendance_push'])
        ->name('attendance-integration.push.userpic');

    Route::get('live/snapshot', [LivePunchFeedController::class, 'snapshot'])
        ->name('attendance-integration.live.snapshot');
});

/*
|--------------------------------------------------------------------------
| ADMS Command Queue API
|--------------------------------------------------------------------------
| Endpoints consumed by the Python ADMS server for two-way sync.
| No authentication required — the Python server runs on localhost.
*/
Route::prefix('api/adms')->group(function () {
    Route::get('commands', [AdmsCommandController::class, 'fetchCommands'])
        ->name('adms.commands.fetch');

    Route::post('commands/result', [AdmsCommandController::class, 'reportResult'])
        ->name('adms.commands.result');

    Route::post('heartbeat', [AdmsCommandController::class, 'heartbeat'])
        ->name('adms.heartbeat');
});
