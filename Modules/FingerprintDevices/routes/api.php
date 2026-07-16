<?php

use Illuminate\Support\Facades\Route;
use Modules\FingerprintDevices\Http\Controllers\DeprecatedPushController;

Route::prefix('fingerprint-push')->group(function () {
    Route::post('attendance', [DeprecatedPushController::class, 'attendance'])
        ->name('api.fingerprint-push.attendance');

    Route::match(['get', 'post'], 'adms', [DeprecatedPushController::class, 'adms'])
        ->name('api.fingerprint-push.adms');
});
