<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;

/**
 * DeviceMonitoringController — device health monitoring endpoint.
 */
class DeviceMonitoringController extends Controller
{
    public function __construct(
        private FingerprintDeviceService $deviceService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('view-fingerprint-devices');

        $stats = $this->deviceService->getDeviceStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
