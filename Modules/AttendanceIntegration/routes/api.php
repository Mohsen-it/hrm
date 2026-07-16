<?php

use Illuminate\Support\Facades\Route;
use Modules\AttendanceIntegration\Http\Controllers\DevicePushController;
use Modules\AttendanceIntegration\Http\Controllers\LivePunchFeedController;
use Modules\AttendanceIntegration\Http\Middleware\LogDeviceRequest;

Route::prefix('api/attendance-integration')->group(function () {
    Route::post('push', [DevicePushController::class, 'handle'])
        ->middleware([LogDeviceRequest::class, 'throttle:attendance_push'])
        ->name('attendance-integration.push');

    Route::match(['get', 'post'], 'push/adms', [DevicePushController::class, 'handle'])
        ->middleware([LogDeviceRequest::class, 'throttle:attendance_push'])
        ->name('attendance-integration.push.adms');

    Route::get('live/snapshot', [LivePunchFeedController::class, 'snapshot'])
        ->name('attendance-integration.live.snapshot');
});
