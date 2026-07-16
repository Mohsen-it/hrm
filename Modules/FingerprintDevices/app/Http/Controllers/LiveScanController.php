<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AttendanceIntegration\Services\LivePunchFeedService;
use Modules\FingerprintDevices\Services\FingerprintDeviceService;

class LiveScanController extends Controller
{
    public function __construct(
        private LivePunchFeedService $feedService,
        private FingerprintDeviceService $deviceService,
    ) {}

    public function index(): Response
    {
        $this->authorize('view-fingerprint-devices');

        return Inertia::render('FingerprintDevices/LiveScan', [
            'recentPunches' => fn () => $this->feedService->getRecentPunches(30),
            'deviceStats' => fn () => $this->deviceService->getDeviceStats(),
        ]);
    }

    public function snapshot(Request $request): JsonResponse
    {
        $this->authorize('view-fingerprint-devices');

        $limit = (int) $request->input('limit', 30);
        $limit = max(1, min(100, $limit));

        return response()->json([
            'punches' => $this->feedService->getRecentPunches($limit),
            'stats' => $this->deviceService->getDeviceStats(),
            'server_time' => now()->toDateTimeString(),
        ]);
    }
}
