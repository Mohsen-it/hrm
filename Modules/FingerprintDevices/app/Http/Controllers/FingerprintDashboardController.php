<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\FingerprintDevices\Http\Resources\FingerprintDeviceResource;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;

/**
 * FingerprintDashboardController — device dashboard with stats.
 */
class FingerprintDashboardController extends Controller
{
    public function __construct(
        private FingerprintDeviceService $deviceService,
    ) {}

    public function __invoke(): Response
    {
        $this->authorize('view-fingerprint-devices');

        return Inertia::render('FingerprintDevices/Dashboard', [
            'stats' => fn () => $this->deviceService->getDeviceStats(),
            'onlineDevices' => fn () => $this->deviceService->getOnlineDevices()
                ->map(fn ($device) => (new FingerprintDeviceResource($device))->toArray(request())),
            'offlineDevices' => fn () => $this->deviceService->getOfflineDevices()
                ->map(fn ($device) => (new FingerprintDeviceResource($device))->toArray(request())),
        ]);
    }
}
