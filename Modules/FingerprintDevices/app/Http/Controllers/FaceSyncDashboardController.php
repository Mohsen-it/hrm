<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\FingerprintDevices\Services\DeviceCommandService;
use Modules\FingerprintDevices\Services\FaceSyncDashboardService;

/**
 * FaceSyncDashboardController — real-time dashboard for monitoring
 * face template sync status across all fingerprint devices.
 */
class FaceSyncDashboardController extends Controller
{
    public function __construct(
        private FaceSyncDashboardService $faceSyncService,
        private DeviceCommandService $commandService,
    ) {}

    public function __invoke(): Response
    {
        $this->authorize('view-fingerprint-devices');

        return Inertia::render('FingerprintDevices/FaceSyncDashboard', [
            'dashboard' => fn () => $this->faceSyncService->getDashboardData(),
        ]);
    }

    /**
     * Re-queue all (or per-device) failed face-template commands for retry.
     */
    public function retryFailedFaceCommands(Request $request): JsonResponse
    {
        $this->authorize('edit-fingerprint-devices');

        $data = $request->validate([
            'device_id' => ['nullable', 'integer', 'exists:fingerprint_devices,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        $result = $this->commandService->retryFailedFaceCommands(
            deviceId: $data['device_id'] ?? null,
            limit: $data['limit'] ?? 200,
            hours: $data['hours'] ?? 168,
        );

        return response()->json([
            'success' => true,
            'requeued' => $result['requeued'],
            'total_failed' => $result['total_failed'],
        ]);
    }
}
