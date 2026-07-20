<?php

use Illuminate\Support\Facades\Route;
use Modules\FingerprintDevices\Http\Controllers\DeprecatedPushController;
use Modules\FingerprintDevices\Http\Controllers\DeviceFullSyncController;
use Modules\FingerprintDevices\Http\Controllers\DeviceMonitoringController;
use Modules\FingerprintDevices\Http\Controllers\FingerprintDashboardController;
use Modules\FingerprintDevices\Http\Controllers\FingerprintDevicesController;
use Modules\FingerprintDevices\Http\Controllers\FingerprintDeviceTypesController;
use Modules\FingerprintDevices\Http\Controllers\FingerprintTemplateController;
use Modules\FingerprintDevices\Http\Controllers\LiveScanController;

Route::middleware(['auth'])->group(function () {
    Route::prefix('fingerprint-devices')->group(function () {
        Route::get('dashboard', FingerprintDashboardController::class)
            ->name('fingerprint-devices.dashboard');

        Route::get('monitoring', DeviceMonitoringController::class)
            ->name('fingerprint-devices.monitoring');

        Route::get('live-scan', [LiveScanController::class, 'index'])
            ->name('fingerprint-devices.live-scan');

        Route::get('live-scan/snapshot', [LiveScanController::class, 'snapshot'])
            ->name('fingerprint-devices.live-scan.snapshot');

        Route::get('sync', [DeviceFullSyncController::class, 'index'])
            ->name('fingerprint-devices.sync');

        Route::post('sync', [DeviceFullSyncController::class, 'sync'])
            ->name('fingerprint-devices.sync.run');

        Route::post('sync/stream', [DeviceFullSyncController::class, 'syncStream'])
            ->name('fingerprint-devices.sync.stream');

        Route::post('sync-all', [DeviceFullSyncController::class, 'syncAll'])
            ->name('fingerprint-devices.sync-all');

        // Push (app → device) routes
        Route::post('sync/push', [DeviceFullSyncController::class, 'push'])
            ->name('fingerprint-devices.sync.push');

        Route::post('sync/push-stream', [DeviceFullSyncController::class, 'pushStream'])
            ->name('fingerprint-devices.sync.push-stream');

        Route::post('sync/push-all', [DeviceFullSyncController::class, 'pushAll'])
            ->name('fingerprint-devices.sync.push-all');

        Route::post('sync/bidirectional', [DeviceFullSyncController::class, 'bidirectional'])
            ->name('fingerprint-devices.sync.bidirectional');

        Route::post('sync/retry-failed/{logId}', [DeviceFullSyncController::class, 'retryFailed'])
            ->whereNumber('logId')
            ->name('fingerprint-devices.sync.retry-failed');

        Route::get('sync/log-status/{logId}', [DeviceFullSyncController::class, 'logStatus'])
            ->whereNumber('logId')
            ->name('fingerprint-devices.sync.log-status');

        Route::resource('devices', FingerprintDevicesController::class)
            ->names('fingerprint-devices')
            ->parameters(['devices' => 'id']);

        Route::post('devices/{id}/test-connection', [FingerprintDevicesController::class, 'testConnection'])
            ->name('fingerprint-devices.test-connection');

        Route::post('devices/{id}/sync-attendance', [FingerprintDevicesController::class, 'syncAttendance'])
            ->name('fingerprint-devices.sync-attendance');

        Route::get('devices/{id}/push-preview', [FingerprintDevicesController::class, 'pushPreview'])
            ->name('fingerprint-devices.push-preview');

        Route::resource('device-types', FingerprintDeviceTypesController::class)
            ->names('fingerprint-device-types')
            ->parameters(['device-types' => 'id'])
            ->except(['show']);

        Route::resource('templates', FingerprintTemplateController::class)
            ->only(['index', 'show', 'update', 'destroy'])
            ->names('fingerprint-templates')
            ->parameters(['templates' => 'id']);
    });
});

Route::prefix('api/fingerprint-push')->group(function () {
    Route::post('attendance', [DeprecatedPushController::class, 'attendance'])
        ->name('fingerprint-push.attendance');

    Route::match(['get', 'post'], 'adms', [DeprecatedPushController::class, 'adms'])
        ->name('fingerprint-push.adms');
});
